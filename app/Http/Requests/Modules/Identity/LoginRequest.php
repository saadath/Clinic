<?php

namespace App\Http\Requests\Modules\Identity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => __('ui.email_required'),
            'email.email' => __('ui.email_invalid'),
            'email.string' => __('ui.email_invalid'),
            'email.max' => __('ui.email_invalid'),
            'password.required' => __('ui.password_required'),
            'password.string' => __('ui.password_required'),
            'password.max' => __('ui.password_invalid'),
        ];
    }
}
