<?php

namespace App\Http\Requests\Modules\Patients;

use App\Models\Doctor;
use App\Models\Patient;
use App\Rules\IranianNationalId;
use App\Services\Patients\PatientInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');
        if (! $this->user()->can($patient ? 'patients.update' : 'patients.create')) {
            return false;
        }
        if ($patient) {
            abort_unless(Patient::visibleTo($this->user())->whereKey($patient->id)->exists(), 404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['national_id', 'mobile', 'emergency_mobile', 'birth_date'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => PatientInput::digits(trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['bail', 'required', 'string', new IranianNationalId, Rule::unique('patients')->ignore($this->route('patient'))],
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/D'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'sex' => ['required', Rule::in(['female', 'male', 'other'])],
            'email' => ['nullable', 'email', 'max:255'],
            'emergency_mobile' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/D'],
            'address' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:3000'],
            'shared_mobile_confirmed' => ['sometimes', 'accepted'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
        ];
        foreach (['occupation', 'emergency_name', 'insurance_name', 'insurance_number', 'referral_source'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }
        $rules['marital_status'] = ['nullable', Rule::in(['single', 'married', 'other'])];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $patient = $this->route('patient');
            if (Patient::where('mobile', $this->input('mobile'))->when($patient, fn ($query) => $query->whereKeyNot($patient->id))->exists() && ! $this->boolean('shared_mobile_confirmed')) {
                $validator->errors()->add('shared_mobile_confirmed', __('patients.shared_warning'));
            }
            if ($this->filled('doctor_id') && ! Doctor::visibleTo($this->user())->where('active', true)->whereKey($this->integer('doctor_id'))->exists()) {
                $validator->errors()->add('doctor_id', __('patients.invalid'));
            }
            if ($patient && $this->filled('doctor_id') && ! $this->user()->hasPermission('patients.view-all') && ! $patient->doctors()->whereKey($this->integer('doctor_id'))->exists()) {
                $validator->errors()->add('doctor_id', __('patients.invalid'));
            }
            if (! $patient && ! $this->user()->hasPermission('patients.view-all') && ! $this->filled('doctor_id')) {
                $validator->errors()->add('doctor_id', __('patients.doctor_required'));
            }
        }];
    }

    public function messages(): array
    {
        return ['national_id.unique' => __('patients.duplicate'), 'mobile.regex' => __('patients.mobile_invalid'), '*' => __('patients.invalid')];
    }
}
