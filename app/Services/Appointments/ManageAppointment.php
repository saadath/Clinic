<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageAppointment
{
    public const TRANSITIONS = ['scheduled' => ['waiting', 'cancelled', 'no_show'], 'waiting' => ['in_progress', 'cancelled'], 'in_progress' => ['completed'], 'completed' => [], 'cancelled' => [], 'no_show' => []];

    public function __construct(private AppointmentAccess $access, private ScheduleAvailability $availability) {}

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Appointment $appointment = null): Appointment
    {
        return DB::transaction(function () use ($actor, $data, $appointment): Appointment {
            $doctorId = $appointment?->doctor_id ?? (int) $data['doctor_id'];
            $doctor = Doctor::whereKey($doctorId)->lockForUpdate()->firstOrFail();
            $this->access->doctor($actor, $doctor->id);
            if ($appointment) {
                $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                $this->revision($appointment, (int) $data['revision']);
                if ($appointment->status !== 'scheduled') {
                    $this->fail('status', 'transition_invalid');
                }
            }
            $patient = Patient::visibleTo($actor)->findOrFail($appointment?->patient_id ?? $data['patient_id']);
            if (! $actor->can('patients.view-all') && ! $patient->doctors()->whereKey($doctor->id)->exists()) {
                abort(404);
            }
            $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Tehran');
            if ($start->isPast()) {
                $this->fail('starts_at', 'past');
            }
            $slot = collect($this->availability->slots($doctor, $start, $appointment?->id))->first(fn ($slot) => $slot['start']->equalTo($start));
            if (! $slot) {
                $this->fail('starts_at', 'outside_schedule');
            }
            $overrideReason = trim($data['override_reason'] ?? '');
            $overCapacity = $slot['booked'] >= $slot['capacity'];
            if ($overCapacity && (! $actor->can('appointments.override') || $overrideReason === '')) {
                $this->fail('starts_at', 'capacity_full');
            }
            if (Appointment::where('doctor_id', $doctorId)->where('patient_id', $patient->id)->whereIn('status', ScheduleAvailability::ACTIVE)
                ->where('starts_at', '<', $slot['end']->utc())->where('ends_at', '>', $start->utc())
                ->when($appointment, fn ($query) => $query->whereKeyNot($appointment->id))->exists()) {
                $this->fail('patient_id', 'duplicate');
            }
            $isNew = $appointment === null;
            $appointment ??= new Appointment(['created_by' => $actor->id, 'status' => 'scheduled', 'revision' => 0]);
            $appointment->fill(['doctor_id' => $doctorId, 'patient_id' => $patient->id, 'starts_at' => $start->utc(), 'ends_at' => $slot['end']->utc(),
                'visit_type' => $data['visit_type'] ?? 'consultation', 'notes' => $data['notes'] ?? null, 'updated_by' => $actor->id,
                'revision' => $appointment->revision + 1, 'override_by' => $overCapacity ? $actor->id : null, 'override_reason' => $overCapacity ? $overrideReason : null]);
            $appointment->save();
            DB::table('appointment_status_events')->insert(['appointment_id' => $appointment->id, 'from_status' => $isNew ? null : 'scheduled', 'to_status' => 'scheduled', 'actor_id' => $actor->id,
                'reason' => $isNew ? ($overCapacity ? $overrideReason : null) : __('appointments.rescheduled').': '.$start->toIso8601String().($overCapacity ? ' — '.$overrideReason : ''), 'occurred_at' => now()]);

            return $appointment;
        }, 5);
    }

    public function transition(User $actor, Appointment $appointment, string $status, int $revision, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($actor, $appointment, $status, $revision, $reason): Appointment {
            Doctor::whereKey($appointment->doctor_id)->lockForUpdate()->firstOrFail();
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, $appointment, 'queue.manage');
            $this->revision($appointment, $revision);
            if (! in_array($status, self::TRANSITIONS[$appointment->status] ?? [], true)) {
                $this->fail('status', 'transition_invalid');
            }
            $today = now('Asia/Tehran')->toDateString();
            $date = $appointment->starts_at->setTimezone('Asia/Tehran')->toDateString();
            if (in_array($status, ['waiting', 'in_progress', 'completed'], true) && $date !== $today) {
                $this->fail('status', 'wrong_day');
            }
            if ($status === 'no_show' && $appointment->starts_at->isFuture()) {
                $this->fail('status', 'too_early');
            }
            if ($status === 'cancelled' && trim($reason ?? '') === '') {
                $this->fail('reason', 'reason_required');
            }
            if ($status === 'in_progress' && Appointment::where('doctor_id', $appointment->doctor_id)->where('status', 'in_progress')->exists()) {
                $this->fail('status', 'doctor_busy');
            }
            if ($status === 'waiting') {
                $number = (int) DB::table('queue_entries')->where('doctor_id', $appointment->doctor_id)->where('local_date', $today)->max('queue_number') + 1;
                DB::table('queue_entries')->insert(['appointment_id' => $appointment->id, 'doctor_id' => $appointment->doctor_id, 'local_date' => $today, 'queue_number' => $number, 'checked_in_at' => now()]);
            }
            if (in_array($status, ['in_progress', 'completed', 'cancelled'], true)) {
                DB::table('queue_entries')->where('appointment_id', $appointment->id)->update([$status === 'in_progress' ? 'called_at' : 'completed_at' => now()]);
            }
            DB::table('appointment_status_events')->insert(['appointment_id' => $appointment->id, 'from_status' => $appointment->status, 'to_status' => $status, 'actor_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now()]);
            $appointment->update(['status' => $status, 'revision' => $appointment->revision + 1, 'updated_by' => $actor->id]);

            return $appointment;
        }, 5);
    }

    private function revision(Appointment $appointment, int $revision): void
    {
        if ($appointment->revision !== $revision) {
            $this->fail('revision', 'stale');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __('appointments.'.$message)]);
    }
}
