<?php

namespace App\Http\Requests\Modules\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('doctors.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('active', true), Rule::unique('doctors')->ignore($this->route('doctor'))],
            'medical_council_number' => ['required', 'string', 'max:50', Rule::unique('doctors')->ignore($this->route('doctor'))],
            'specialty' => ['required', 'string', 'max:255'],
            'subspecialty' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:10000'],
            'visit_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'visit_price_rial' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['*' => __('staff.invalid')];
    }
}
