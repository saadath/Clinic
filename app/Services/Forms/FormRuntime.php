<?php

namespace App\Services\Forms;

use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormRuntime
{
    public function __construct(private FieldTypeRegistry $types, private FormAccess $access) {}

    public function validate(User $user, FormTemplate $template, array $schema, array $values): array
    {
        $fields = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field;
            }
        }
        foreach ($values as $key => $value) {
            if (! isset($fields[$key]) || ! $this->access->fieldEditable($user, $template, $fields[$key]) || ! $fields[$key]['active'] || in_array($fields[$key]['type'], ['calculated', 'heading', 'description', 'divider'], true)) {
                $this->invalid((string) $key);
            }
        }
        $context = $values;
        foreach ($fields as $key => $field) {
            $context[$key] ??= $field['default'];
        }
        foreach ($fields as $key => $field) {
            if ($field['type'] === 'calculated') {
                $context[$key] = $this->calculateField($key, $fields, $context);
            }
        }
        $result = [];
        foreach ($fields as $key => $field) {
            if (! $field['active'] || ! $this->access->fieldVisible($user, $template, $field) || ! $this->condition($field['condition'], $context)) {
                continue;
            }
            if ($field['type'] === 'calculated') {
                if ($context[$key] === null) {
                    $this->invalid($key, __('forms.calculation_error'));
                }
                $result[$key] = $context[$key];

                continue;
            }
            if (! $this->access->fieldEditable($user, $template, $field)) {
                continue;
            }
            $value = $context[$key] ?? null;
            if ($field['type'] === 'repeater') {
                $result[$key] = $this->rows($user, $template, $field, $value ?? []);
            } elseif ($this->types->get($field['type'])['kind'] !== 'display') {
                $result[$key] = $this->value($field, $value, $key);
            }
        }

        return $result;
    }

    private function rows(User $user, FormTemplate $template, array $field, mixed $rows): array
    {
        if (! is_array($rows) || count($rows) > 30 || ($field['required'] && count($rows) === 0)) {
            $this->invalid($field['key']);
        }
        $result = [];
        $children = collect($field['children'])->keyBy('key');
        foreach ($rows as $id => $row) {
            if (! preg_match('/^[a-zA-Z0-9_-]{8,64}$/D', (string) $id) || ! is_array($row)) {
                $this->invalid($field['key']);
            }
            foreach ($row as $key => $value) {
                if (! isset($children[$key]) || ! $children[$key]['active'] || ! $this->access->fieldEditable($user, $template, $children[$key])) {
                    $this->invalid($field['key'].'.'.$id.'.'.$key);
                }
            }
            $result[$id] = [];
            foreach ($children as $key => $child) {
                if ($child['active'] && $this->access->fieldEditable($user, $template, $child) && $this->types->get($child['type'])['kind'] !== 'display') {
                    $result[$id][$key] = $this->value($child, $row[$key] ?? $child['default'], $field['key'].'.'.$id.'.'.$key);
                }
            }
        }

        return $result;
    }

    private function value(array $field, mixed $value, string $path): mixed
    {
        $kind = $this->types->get($field['type'])['kind'];
        $rules = [$field['required'] ? 'required' : 'nullable'];
        if ($kind === 'file') {
            if ($value !== null && $value !== '' && $value !== []) {
                $this->invalid($path, __('forms.files_pending'));
            }
            if ($field['required']) {
                $this->invalid($path, __('forms.files_pending'));
            }

            return null;
        }
        if (in_array($field['type'], ['multi_select', 'checkbox'], true)) {
            $rules[] = 'array';
            $rules[] = 'max:100';
            Validator::make(['value' => $value], ['value' => $rules, 'value.*' => ['string', 'distinct', Rule::in($field['options'])]])->validate();

            return $value;
        }
        if ($kind === 'number') {
            $rules[] = $field['type'] === 'integer' ? 'integer' : 'numeric';
            $rules[] = 'between:-1000000000,1000000000';
        } else {
            $rules[] = 'string';
            $rules[] = 'max:10000';
        }
        if ($field['min'] !== null) {
            $rules[] = 'min:'.$field['min'];
        }
        if ($field['max'] !== null) {
            $rules[] = 'max:'.$field['max'];
        }
        $specific = ['email' => 'email', 'url' => 'url:http,https', 'phone' => 'regex:/^\+?[0-9 ()-]{7,20}$/D', 'date' => 'date_format:Y-m-d', 'datetime' => 'date_format:Y-m-d\TH:i', 'time' => 'date_format:H:i', 'color' => 'regex:/^#[0-9a-fA-F]{6}$/D'];
        if (isset($specific[$field['type']])) {
            $rules[] = $specific[$field['type']];
        }
        if ($kind === 'choice') {
            $rules[] = Rule::in($field['options']);
        }
        $validator = Validator::make(['value' => $value], ['value' => $rules]);
        if ($validator->fails()) {
            $this->invalid($path);
        }

        return $kind === 'number' && $value !== null && $value !== '' ? ($field['type'] === 'integer' ? (int) $value : (float) $value) : ($field['type'] === 'rich_text' && is_string($value) ? strip_tags($value) : $value);
    }

    public function condition(?array $node, array $values): bool
    {
        if ($node === null) {
            return true;
        }
        if (in_array($node['op'], ['and', 'or'], true)) {
            $results = array_map(fn ($child) => $this->condition($child, $values), $node['conditions']);

            return $node['op'] === 'and' ? ! in_array(false, $results, true) : in_array(true, $results, true);
        }
        $value = $values[$node['field']] ?? null;

        return match ($node['op']) {
            'exists' => $value !== null && $value !== '' && $value !== [],
            'equals' => is_scalar($value) && (string) $value === (string) $node['value'],
            'in' => is_scalar($value) && in_array((string) $value, array_map('strval', $node['value']), true),
            'gt' => is_numeric($value) && (float) $value > (float) $node['value'],
        };
    }

    private function calculateField(string $key, array $fields, array $values, int $depth = 0): ?float
    {
        if ($depth > 30) {
            return null;
        }
        $field = $fields[$key];
        if (! $field['active']) {
            return null;
        }
        if ($field['type'] !== 'calculated') {
            return is_numeric($values[$key] ?? null) ? (float) $values[$key] : null;
        }

        return $this->expression($field['calculation'], $fields, $values, $depth + 1);
    }

    private function expression(array $node, array $fields, array $values, int $depth): ?float
    {
        if (isset($node['field'])) {
            return $this->calculateField($node['field'], $fields, $values, $depth + 1);
        }
        if (isset($node['number'])) {
            return (float) $node['number'];
        }
        $left = $this->expression($node['left'], $fields, $values, $depth + 1);
        $right = $this->expression($node['right'], $fields, $values, $depth + 1);
        if ($left === null || $right === null || ($node['op'] === '/' && $right == 0)) {
            return null;
        }
        $result = match ($node['op']) { '+' => $left + $right, '-' => $left - $right, '*' => $left * $right, '/' => $left / $right };

        return is_finite($result) && abs($result) <= 1000000000 ? round($result, 6) : null;
    }

    private function invalid(string $path, ?string $message = null): never
    {
        throw ValidationException::withMessages(['values.'.$path => $message ?? __('forms.invalid_value')]);
    }
}
