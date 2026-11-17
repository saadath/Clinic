<?php

namespace App\Http\Requests\Modules\Identity;

use App\Services\PersianDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorStaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignments.manage');
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')->where('active', true)],
            'staff_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('active', true)],
            'assignment_type' => ['required', Rule::in(['receptionist', 'assistant'])],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('date_calendar') === 'persian') {
            foreach (['starts_at', 'ends_at'] as $field) {
                if (is_string($this->input($field)) && $this->filled($field)) {
                    $this->merge([$field => PersianDate::parse($this->input($field), true) ?? $this->input($field)]);
                }
            }
        }
    }

    public function messages(): array
    {
        return ['starts_at.*' => __('staff.assignment_start_invalid'), 'ends_at.*' => __('staff.assignment_end_invalid'), '*' => __('staff.invalid')];
    }
}
