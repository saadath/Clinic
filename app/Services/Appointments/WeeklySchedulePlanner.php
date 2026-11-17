<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Patients\PatientInput;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WeeklySchedulePlanner
{
    public function __construct(private PlannerCalendar $calendar, private ScheduleAvailability $availability, private AppointmentAccess $access) {}

    public function validateShifts(array $shifts): array
    {
        foreach ($shifts as &$shift) {
            if (! is_array($shift)) {
                $this->fail('ساختار شیفت معتبر نیست.');
            }
            foreach (['local_start', 'local_end', 'break_start', 'break_end', 'slot_minutes', 'capacity'] as $field) {
                if (isset($shift[$field]) && is_scalar($shift[$field])) {
                    $shift[$field] = PatientInput::digits(trim((string) $shift[$field]));
                }
            }
        }
        unset($shift);
        Validator::make(['shifts' => $shifts], [
            'shifts' => ['array', 'max:6'],
            'shifts.*' => ['array:key,local_start,local_end,slot_minutes,capacity,break_start,break_end'],
            'shifts.*.key' => ['required', 'regex:/^[a-zA-Z0-9_-]{1,50}$/D'],
            'shifts.*.local_start' => ['required', 'date_format:H:i'],
            'shifts.*.local_end' => ['required', 'date_format:H:i'],
            'shifts.*.slot_minutes' => ['required', 'integer', 'between:5,240'],
            'shifts.*.capacity' => ['required', 'integer', 'between:1,50'],
            'shifts.*.break_start' => ['nullable', 'date_format:H:i'],
            'shifts.*.break_end' => ['nullable', 'date_format:H:i'],
        ])->validate();
        foreach ($shifts as &$shift) {
            $shift['slot_minutes'] = (int) $shift['slot_minutes'];
            $shift['capacity'] = (int) $shift['capacity'];
        }
        unset($shift);
        usort($shifts, fn (array $a, array $b) => strcmp($a['local_start'], $b['local_start']));
        $previousEnd = null;
        $keys = [];
        foreach ($shifts as $shift) {
            if ($shift['local_start'] >= $shift['local_end'] || ($previousEnd !== null && $shift['local_start'] < $previousEnd) || in_array($shift['key'], $keys, true)) {
                $this->fail('شیفت‌ها باید بدون هم‌پوشانی و با ساعت پایان پس از شروع باشند.');
            }
            $hasStart = ! empty($shift['break_start']);
            $hasEnd = ! empty($shift['break_end']);
            if ($hasStart !== $hasEnd || ($hasStart && ($shift['break_start'] < $shift['local_start'] || $shift['break_end'] > $shift['local_end'] || $shift['break_start'] >= $shift['break_end']))) {
                $this->fail('بازه استراحت باید کامل و داخل شیفت باشد.');
            }
            $previousEnd = $shift['local_end'];
            $keys[] = $shift['key'];
        }

        return $shifts;
    }

    public function expand(array $data): array
    {
        $range = $this->calendar->range($data);
        $weekdays = [];
        foreach ($data['days'] as $day) {
            $weekdays[$day['weekday']] = $this->validateShifts($day['shifts']);
        }
        $days = [];
        foreach ($range['weeks'] as $index => $week) {
            for ($date = CarbonImmutable::parse($week['start'], 'Asia/Tehran'); $date->toDateString() <= $week['end']; $date = $date->addDay()) {
                $days[] = ['date' => $date->toDateString(), 'week' => $index, 'shifts' => $weekdays[$date->dayOfWeek]];
            }
        }

        return ['start' => $range['start']->toDateString(), 'end' => $range['end']->toDateString(), 'weeks' => $range['weeks'], 'days' => $days,
            'working_days' => count(array_filter($days, fn ($day) => count($day['shifts']) > 0)), 'shift_count' => array_sum(array_map(fn ($day) => count($day['shifts']), $days))];
    }

    public function create(User $actor, array $data): array
    {
        $this->access->schedule($actor, $data['doctor_id']);
        $expanded = $this->expand($data);

        return DB::transaction(function () use ($actor, $data, $expanded): array {
            $doctor = Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            $this->access->schedule($actor, $doctor->id);
            if (DB::table('doctor_schedule_days')->where('doctor_id', $doctor->id)->whereBetween('local_date', [$expanded['start'], $expanded['end']])->exists()) {
                $this->fail('این بازه قبلاً برنامه مستقل دارد؛ روز موردنظر را از تقویم ویرایش کنید.');
            }
            $template = DB::table('doctor_schedule_templates')->insertGetId(['doctor_id' => $doctor->id, 'starts_on' => $expanded['start'], 'ends_on' => $expanded['end'], 'base_json' => json_encode($data['days'], JSON_THROW_ON_ERROR), 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $weeks = [];
            foreach ($expanded['weeks'] as $index => $week) {
                $weeks[$index] = DB::table('doctor_schedule_weeks')->insertGetId(['template_id' => $template, 'starts_on' => $week['start'], 'ends_on' => $week['end']]);
            }
            $conflicts = [];
            foreach ($expanded['days'] as $day) {
                $id = DB::table('doctor_schedule_days')->insertGetId(['week_id' => $weeks[$day['week']], 'doctor_id' => $doctor->id, 'local_date' => $day['date'], 'revision' => 1, 'base_json' => json_encode($day['shifts'], JSON_THROW_ON_ERROR), 'final_json' => json_encode($day['shifts'], JSON_THROW_ON_ERROR), 'change_kind' => 'normal']);
                $this->persistShifts($id, $day['shifts']);
                $log = $this->log($actor, $doctor->id, $day['date'], 'create', null, $day['shifts'], $data['reason']);
                $conflicts = [...$conflicts, ...$this->detect($actor, $doctor, $day['date'], $log, $data['reason'])];
            }
            $this->confirm($actor, $data, $conflicts);

            return ['template_id' => $template, 'conflicts' => count($conflicts)] + $expanded;
        }, 5);
    }

    public function changeDay(User $actor, array $data): array
    {
        $this->access->schedule($actor, $data['doctor_id']);

        return DB::transaction(function () use ($actor, $data): array {
            $doctor = Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            $this->access->schedule($actor, $doctor->id);
            $dates = array_values(array_unique($data['dates']));
            $conflicts = [];
            foreach ($dates as $inputDate) {
                $date = $this->calendar->date($inputDate)->toDateString();
                $day = DB::table('doctor_schedule_days')->where('doctor_id', $doctor->id)->where('local_date', $date)->lockForUpdate()->first();
                if (! $day) {
                    $this->fail('برای تاریخ انتخابی ابتدا برنامه هفتگی ایجاد کنید.');
                }
                $revision = $data['revisions'][$date] ?? (count($dates) === 1 ? ($data['revision'] ?? 0) : 0);
                if ((int) $revision !== (int) $day->revision) {
                    $this->fail('برنامه روز تغییر کرده است؛ تقویم را تازه کنید.');
                }
                $before = json_decode($day->final_json, true, flags: JSON_THROW_ON_ERROR);
                $shifts = match ($data['kind']) {
                    'closed' => [],
                    'replace' => $data['shifts'],
                    'add' => [...$before, ...$data['shifts']],
                    'remove' => array_values(array_filter($before, fn ($shift) => $shift['key'] !== $data['shift_key'])),
                    default => $before,
                };
                $shifts = $this->validateShifts($shifts);
                DB::table('doctor_schedule_days')->where('id', $day->id)->update(['final_json' => json_encode($shifts, JSON_THROW_ON_ERROR), 'revision' => $day->revision + 1, 'change_kind' => $data['kind']]);
                $this->persistShifts($day->id, $shifts);
                $log = $this->log($actor, $doctor->id, $date, $data['kind'], $before, $shifts, $data['reason']);
                $conflicts = [...$conflicts, ...$this->detect($actor, $doctor, $date, $log, $data['reason'])];
            }
            $this->confirm($actor, $data, $conflicts);

            return ['changed_days' => count($dates), 'conflicts' => count($conflicts)];
        }, 5);
    }

    public function log(User $actor, ?int $doctor, ?string $date, string $action, ?array $before, array $after, string $reason): int
    {
        return DB::table('schedule_change_logs')->insertGetId(['doctor_id' => $doctor, 'local_date' => $date, 'action' => $action, 'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'reason' => $reason, 'actor_id' => $actor->id, 'created_at' => now()]);
    }

    public function detect(User $actor, Doctor $doctor, string $date, int $log, string $reason): array
    {
        $start = CarbonImmutable::parse($date, 'Asia/Tehran');
        $bookings = Appointment::with('patient')->where('doctor_id', $doctor->id)->whereIn('status', ['scheduled', 'waiting', 'in_progress'])->where('starts_at', '<', $start->addDay()->utc())->where('ends_at', '>', $start->utc())->get();
        $slots = $this->availability->slots($doctor, $start);
        $affected = [];
        foreach ($bookings as $appointment) {
            $slot = collect($slots)->first(fn ($slot) => $slot['start']->equalTo($appointment->starts_at) && $slot['end']->equalTo($appointment->ends_at));
            if ($slot && $slot['booked'] <= $slot['capacity']) {
                continue;
            }
            $affected[] = ['appointment_id' => $appointment->id, 'patient' => $appointment->patient->first_name.' '.$appointment->patient->last_name, 'starts_at' => $appointment->starts_at->toIso8601String()];
            DB::table('appointment_schedule_conflicts')->insert(['appointment_id' => $appointment->id, 'change_log_id' => $log, 'status' => 'review_required', 'reason' => $reason, 'previous_booking' => json_encode(['starts_at' => $appointment->starts_at->toIso8601String(), 'ends_at' => $appointment->ends_at->toIso8601String(), 'status' => $appointment->status], JSON_THROW_ON_ERROR), 'detected_at' => now(), 'revision' => 1]);
        }

        return $affected;
    }

    public function confirm(User $actor, array $data, array $conflicts): void
    {
        if ($conflicts && (empty($data['confirm_conflicts']) || ! $actor->can('appointments.override'))) {
            throw ValidationException::withMessages(['conflicts' => 'توجه: '.count($conflicts).' نوبت متأثر است. نوبت‌ها حذف نمی‌شوند. ادامه فقط با تأیید کاربر دارای مجوز افزایش ظرفیت مجاز است.', 'affected' => json_encode($conflicts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }
    }

    private function persistShifts(int $day, array $shifts): void
    {
        DB::table('doctor_schedule_shifts')->where('day_id', $day)->delete();
        foreach ($shifts as $shift) {
            DB::table('doctor_schedule_shifts')->insert(['day_id' => $day, 'shift_key' => $shift['key'], 'local_start' => $shift['local_start'], 'local_end' => $shift['local_end'], 'slot_minutes' => $shift['slot_minutes'], 'capacity' => $shift['capacity'], 'break_start' => $shift['break_start'] ?? null, 'break_end' => $shift['break_end'] ?? null]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['schedule' => $message]);
    }
}
