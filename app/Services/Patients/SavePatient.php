<?php

namespace App\Services\Patients;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePatient
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Patient $patient = null): Patient
    {
        try {
            return DB::transaction(function () use ($actor, $data, $patient): Patient {
                if ($patient) {
                    $patient = Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
                    abort_unless(Patient::visibleTo($actor)->whereKey($patient->id)->exists(), 404);
                }
                $patient ??= new Patient;
                $patient->fill(collect($data)->except(['doctor_id', 'shared_mobile_confirmed'])->all());
                if (! $patient->exists) {
                    $patient->created_by = $actor->id;
                }
                $patient->updated_by = $actor->id;
                $patient->save();
                if (! empty($data['doctor_id'])) {
                    if (! $patient->doctors()->whereKey($data['doctor_id'])->exists()) {
                        $patient->doctors()->attach($data['doctor_id'], ['granted_by' => $actor->id]);
                    }
                }

                return $patient;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['national_id' => __('patients.duplicate')]);
        }
    }
}
