<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AppointmentAccess
{
    public function doctors(User $user): Builder
    {
        return Doctor::visibleTo($user)->where('active', true)->whereHas('user', fn ($query) => $query->where('active', true));
    }

    public function query(User $user): Builder
    {
        return Appointment::whereIn('doctor_id', $this->doctors($user)->select('doctors.id'));
    }

    public function doctor(User $user, int $doctorId, string $ability = 'appointments.manage'): Doctor
    {
        abort_unless($user->can($ability), 403);

        return $this->doctors($user)->findOrFail($doctorId);
    }

    public function authorize(User $user, Appointment $appointment, string $ability = 'appointments.manage'): void
    {
        $this->doctor($user, $appointment->doctor_id, $ability);
    }

    public function schedule(User $user, int $doctorId): Doctor
    {
        $doctor = $this->doctor($user, $doctorId, 'schedules.manage');
        abort_unless($user->can('doctors.manage') || $doctor->user_id === $user->id, 403);

        return $doctor;
    }
}
