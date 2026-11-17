<?php

namespace App\Http\Requests\Modules\Forms;

use App\Services\Forms\FormAccess;
use Illuminate\Foundation\Http\FormRequest;

class FormSchemaRequest extends FormRequest
{
    public function authorize(FormAccess $access): bool
    {
        $access->authorize($this->user(), $this->route('version')->template, true);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('schema'))) {
            $schema = json_decode($this->input('schema'), true);
            $this->merge(['schema' => $schema]);
        }
    }

    public function rules(): array
    {
        return ['schema' => ['required', 'array'], 'revision' => ['required', 'integer', 'min:1']];
    }
}
