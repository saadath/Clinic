<?php

namespace App\Http\Requests\Modules\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'mobile' => ['nullable', 'regex:/^09[0-9]{9}$/', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:12', 'max:1024', 'confirmed'],
            'active' => ['required', 'boolean'],
            'roles' => ['present', 'array', 'max:20'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if (is_string($this->input('mobile'))) {
            $this->merge(['mobile' => strtr($this->input('mobile'), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')))]);
        }
        $this->merge(['roles' => $this->input('roles', [])]);
    }

    public function messages(): array
    {
        return ['*' => __('staff.invalid')];
    }
}
