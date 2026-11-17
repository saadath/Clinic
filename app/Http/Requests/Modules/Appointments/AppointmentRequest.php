<?php

namespace App\Http\Requests\Modules\Appointments;

use App\Services\PersianDate;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appointments.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('date_calendar') === 'persian' && is_string($this->input('starts_at'))) {
            $this->merge(['starts_at' => PersianDate::parse($this->input('starts_at'), true) ?? 'invalid']);
        }
    }

    public function rules(): array
    {
        return [
            'doctor_id' => [$this->route('appointment') ? 'sometimes' : 'required', 'integer'],
            'patient_id' => [$this->route('appointment') ? 'sometimes' : 'required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'revision' => [$this->route('appointment') ? 'required' : 'nullable', 'integer', 'min:1'],
            'visit_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(fn (string $field): array => [$field => __('appointments.'.$field)])->all();
    }
}
