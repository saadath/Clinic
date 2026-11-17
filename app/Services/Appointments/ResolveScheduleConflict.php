<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveScheduleConflict
{
    public const STATUSES = ['review_required', 'awaiting_contact', 'awaiting_new_time', 'time_proposed', 'patient_confirmed', 'patient_cancelled', 'resolved'];

    public function __construct(private AppointmentAccess $access, private ManageAppointment $manage, private ScheduleAvailability $availability, private WeeklySchedulePlanner $planner) {}

    public function update(User $actor, int $id, array $data): void
    {
        $initial = DB::table('appointment_schedule_conflicts')->find($id);
        abort_unless($initial, 404);
        $appointment = Appointment::findOrFail($initial->appointment_id);
        $this->access->authorize($actor, $appointment, 'queue.manage');
        DB::transaction(function () use ($actor, $id, $data, $appointment): void {
            $doctor = Doctor::whereKey($appointment->doctor_id)->lockForUpdate()->firstOrFail();
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $conflict = DB::table('appointment_schedule_conflicts')->where('id', $id)->lockForUpdate()->first();
            $this->access->authorize($actor, $appointment, 'queue.manage');
            if ($conflict->resolved_at || $conflict->revision !== (int) $data['revision']) {
                $this->fail('این تعارض تغییر کرده یا تعیین تکلیف شده است؛ صفحه را تازه کنید.');
            }
            $status = $data['status'];
            if (in_array($status, ['patient_confirmed', 'patient_cancelled'], true) && empty($data['patient_response'])) {
                $this->fail('پاسخ و تأیید بیمار باید ثبت شود.');
            }
            if ($status === 'time_proposed') {
                $start = CarbonImmutable::parse($data['proposed_start'], 'Asia/Tehran');
                $slot = collect($this->availability->slots($doctor, $start, $appointment->id))->first(fn ($slot) => $slot['start']->equalTo($start) && $slot['booked'] < $slot['capacity']);
                if (! $slot || $start->isPast()) {
                    $this->fail('زمان پیشنهادی باید در آینده و دارای ظرفیت آزاد باشد.');
                }
                DB::table('appointment_reschedule_requests')->insert(['conflict_id' => $id, 'proposed_start' => $start->utc(), 'proposed_end' => $slot['end']->utc(), 'status' => 'proposed', 'created_by' => $actor->id, 'created_at' => now()]);
            }
            if ($status === 'patient_confirmed') {
                $proposal = DB::table('appointment_reschedule_requests')->where('conflict_id', $id)->where('id', $data['proposal_id'] ?? 0)->where('status', 'proposed')->lockForUpdate()->first();
                if (! $proposal) {
                    $this->fail('پیشنهاد معتبر انتخاب کنید.');
                }
                $start = CarbonImmutable::parse($proposal->proposed_start, 'UTC')->setTimezone('Asia/Tehran');
                $slot = collect($this->availability->slots($doctor, $start, $appointment->id))->first(fn ($slot) => $slot['start']->equalTo($start));
                if (! $slot || ! $slot['end']->equalTo(CarbonImmutable::parse($proposal->proposed_end, 'UTC')) || $slot['booked'] >= $slot['capacity']) {
                    $this->fail('زمان پیشنهادی تغییر کرده یا ظرفیت آن تکمیل شده است؛ پیشنهاد جدید ثبت کنید.');
                }
                $this->manage->save($actor, ['revision' => $appointment->revision, 'starts_at' => $start->format('Y-m-d\TH:i'), 'visit_type' => $appointment->visit_type, 'notes' => $appointment->notes], $appointment);
                DB::table('appointment_reschedule_requests')->where('id', $proposal->id)->update(['status' => 'confirmed', 'patient_response' => $data['patient_response'], 'confirmed_at' => now()]);
            }
            if ($status === 'patient_cancelled') {
                $this->manage->transition($actor, $appointment, 'cancelled', $appointment->revision, $data['patient_response']);
            }
            if ($status === 'resolved' && in_array($appointment->status, ['scheduled', 'waiting', 'in_progress'], true)) {
                $valid = collect($this->availability->slots($doctor, $appointment->starts_at))->contains(fn ($slot) => $slot['start']->equalTo($appointment->starts_at) && $slot['end']->equalTo($appointment->ends_at) && $slot['booked'] <= $slot['capacity']);
                if (! $valid) {
                    $this->fail('نوبت هنوز با برنامه تعارض دارد؛ ابتدا آن را با تأیید بیمار جابه‌جا یا لغو کنید.');
                }
            }
            $resolved = in_array($status, ['patient_confirmed', 'patient_cancelled', 'resolved'], true);
            DB::table('appointment_schedule_conflicts')->where('id', $id)->update(['status' => $status, 'responsible_user_id' => $actor->id, 'revision' => $conflict->revision + 1, 'resolved_at' => $resolved ? now() : null]);
            $this->planner->log($actor, $doctor->id, $appointment->starts_at->setTimezone('Asia/Tehran')->toDateString(), 'conflict_update', ['status' => $conflict->status], ['conflict_id' => $id, 'status' => $status, 'patient_response' => $data['patient_response'] ?? null], 'رسیدگی به تعارض');
        }, 5);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['conflict' => $message]);
    }
}
