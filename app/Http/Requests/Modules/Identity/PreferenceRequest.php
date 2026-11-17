<?php

namespace App\Http\Requests\Modules\Identity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferenceRequest extends FormRequest
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
            'locale' => ['required', Rule::in(['fa', 'en'])],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'locale.required' => __('ui.preference_invalid'),
            'locale.in' => __('ui.preference_invalid'),
            'theme.required' => __('ui.preference_invalid'),
            'theme.in' => __('ui.preference_invalid'),
        ];
    }
}
