<?php

namespace App\Http\Requests\Modules\Identity;

use App\Services\Identity\AccessCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]+$/', Rule::unique('roles')->ignore($this->route('role'))],
            'label' => ['required', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(AccessCatalog::PERMISSIONS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['permissions' => $this->input('permissions', [])]);
    }

    public function messages(): array
    {
        return ['*' => __('staff.invalid')];
    }
}
