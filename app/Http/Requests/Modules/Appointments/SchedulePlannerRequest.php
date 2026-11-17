<?php

namespace App\Http\Requests\Modules\Appointments;

use App\Services\Appointments\AppointmentAccess;
use App\Services\Patients\PatientInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchedulePlannerRequest extends FormRequest
{
    public function authorize(AppointmentAccess $access): bool
    {
        $access->schedule($this->user(), (int) $this->input('doctor_id'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payload'))) {
            $decoded = json_decode($this->input('payload'), true);
            if (is_array($decoded)) {
                $this->merge($decoded);
            }
        }
        $days = $this->input('days', []);
        if (is_array($days)) {
            foreach ($days as &$day) {
                if (! is_array($day) || ! is_array($day['shifts'] ?? null)) {
                    continue;
                }
                foreach ($day['shifts'] ?? [] as $index => $shift) {
                    if (! is_array($shift)) {
                        continue;
                    }
                    foreach (['local_start', 'local_end', 'break_start', 'break_end', 'slot_minutes', 'capacity'] as $field) {
                        if (isset($shift[$field]) && is_scalar($shift[$field])) {
                            $day['shifts'][$index][$field] = $shift[$field] !== '' ? PatientInput::digits(trim((string) $shift[$field])) : null;
                        }
                    }
                }
            }
            unset($day);
            $this->merge(['days' => $days]);
        }
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer'],
            'starts_on' => ['required', 'string'], 'ends_on' => ['required_if:period,custom', 'nullable', 'string'], 'valid_until' => ['nullable', 'string'],
            'period' => ['required', Rule::in(['week', 'two_weeks', 'months', 'custom'])], 'months' => ['exclude_unless:period,months', 'required', Rule::in([1, 2, 3, 6, 9, 12])],
            'reason' => ['required', 'string', 'max:500'], 'confirm_conflicts' => ['sometimes', 'boolean'],
            'days' => ['required', 'array', 'size:7'], 'days.*' => ['array:weekday,shifts'], 'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.shifts' => ['present', 'array', 'max:6'], 'days.*.shifts.*' => ['array:key,local_start,local_end,slot_minutes,capacity,break_start,break_end'],
            'days.*.shifts.*.key' => ['required', 'regex:/^[a-zA-Z0-9_-]{1,50}$/D'],
            'days.*.shifts.*.local_start' => ['required', 'date_format:H:i'], 'days.*.shifts.*.local_end' => ['required', 'date_format:H:i'],
            'days.*.shifts.*.slot_minutes' => ['required', 'integer', 'between:5,240'], 'days.*.shifts.*.capacity' => ['required', 'integer', 'between:1,50'],
            'days.*.shifts.*.break_start' => ['nullable', 'date_format:H:i'], 'days.*.shifts.*.break_end' => ['nullable', 'date_format:H:i'],
        ];
    }
}
