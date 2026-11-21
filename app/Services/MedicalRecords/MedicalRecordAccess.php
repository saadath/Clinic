<?php

namespace App\Services\MedicalRecords;

use App\Models\Doctor;
use App\Models\MedicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MedicalRecordAccess
{
    public function patients(User $user): Builder
    {
        if ($user->can('medical-records.view-all')) {
            return Patient::query();
        }

        if ($user->can('medical-records.view') && $user->hasRole('doctor') && Doctor::query()->where('user_id', $user->id)->where('active', true)->exists()) {
            return Patient::visibleTo($user);
        }

        return Patient::query()->where(function (Builder $query) use ($user): void {
            $query->whereHas('medicalEncounters', fn (Builder $encounters) => $encounters->whereIn('doctor_id', $this->doctorIds($user)))
                ->orWhereHas('doctors', fn (Builder $doctors) => $doctors->whereIn('doctors.id', $this->doctorIds($user)));
        });
    }

    public function encounters(User $user): Builder
    {
        if ($user->can('medical-records.view-all')) {
            return MedicalEncounter::query();
        }

        return MedicalEncounter::query()->whereIn('doctor_id', $this->doctorIds($user));
    }

    public function authorizePatient(User $user, Patient $patient): void
    {
        abort_unless($user->can('medical-records.view'), 403);
        abort_unless($this->patients($user)->whereKey($patient->id)->exists(), 404);
    }

    public function authorizeEncounter(User $user, MedicalEncounter $encounter, bool $write = false): void
    {
        abort_unless($user->can($write ? 'medical-records.manage' : 'medical-records.view'), 403);
        abort_unless($this->encounters($user)->whereKey($encounter->id)->exists(), 404);
        if ($write) {
            abort_unless($this->canManageEncounter($user, $encounter), 403);
        }
    }

    public function canManageEncounter(User $user, MedicalEncounter $encounter): bool
    {
        return $user->can('medical-records.manage') && (
            $user->can('medical-records.manage-all')
            || ($user->hasRole('doctor') && $encounter->doctor->user_id === $user->id)
        );
    }

    public function doctor(User $user, int $doctorId): Doctor
    {
        abort_unless($user->can('medical-records.manage'), 403);
        $doctor = Doctor::whereKey($doctorId)->where('active', true)->firstOrFail();
        abort_unless($user->can('medical-records.manage-all') || ($doctor->user_id === $user->id && $user->hasRole('doctor')), 403);

        return $doctor;
    }

    private function doctorIds(User $user): Builder
    {
        return Doctor::query()->where('active', true)->whereHas('user', fn (Builder $account) => $account->where('active', true))
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)->orWhereHas('assignments', fn (Builder $assignments) => $assignments->currentFor($user));
            })->select('doctors.id');
    }
}
