<?php

namespace App\Services\Forms;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ValidateFormSchema
{
    public function validate(array $schema): array
    {
        Validator::make(['schema' => $schema], [
            'schema' => ['required', 'array:sections'],
            'schema.sections' => ['required', 'array', 'min:1', 'max:20'],
            'schema.sections.*' => ['required', 'array:key,title,columns,fields'],
            'schema.sections.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,49}$/D', 'distinct'],
            'schema.sections.*.title' => ['required', 'string', 'max:200'],
            'schema.sections.*.columns' => ['required', 'integer', 'between:1,3'],
            'schema.sections.*.fields' => ['present', 'array', 'max:100'],
        ])->validate();
        $fields = [];
        $childKeys = [];
        foreach ($schema['sections'] as &$section) {
            foreach ($section['fields'] as &$field) {
                $field = $this->field($field, false);
                $this->register($fields, $field);
                foreach ($field['children'] as $child) {
                    $this->register($fields, $child);
                    $childKeys[] = $child['key'];
                }
            }
            unset($field);
        }
        unset($section);
        if (count($fields) > 200) {
            $this->invalid();
        }
        $edges = [];
        foreach ($fields as $key => $field) {
            $dependencies = [];
            if ($field['condition'] !== null) {
                $dependencies = $this->condition($field['condition'], $fields);
            }
            if ($field['type'] === 'calculated') {
                $dependencies = [...$dependencies, ...$this->expression($field['calculation'], $fields)];
            }
            foreach ($dependencies as $dependency) {
                if (in_array($dependency, $childKeys, true)) {
                    $this->invalid();
                }
                if ($fields[$dependency]['confidentiality'] === 'confidential' && $field['confidentiality'] !== 'confidential') {
                    $this->invalid();
                }
                if ($field['receptionist_visible'] && ! $fields[$dependency]['receptionist_visible']) {
                    $this->invalid();
                }
            }
            $edges[$key] = $dependencies;
        }
        foreach (array_keys($fields) as $key) {
            $this->cycle($key, $edges, []);
        }

        return $schema;
    }

    private function field(mixed $field, bool $child): array
    {
        Validator::make(['field' => $field], [
            'field' => ['required', 'array:key,type,label,description,width,required,active,default,min,max,options,options_layout,unit,condition,calculation,children,confidentiality,assistant_editable,receptionist_visible,exportable,ai'],
            'field.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,49}$/D'],
            'field.type' => ['required', Rule::in(array_keys((new FieldTypeRegistry)->all()))],
            'field.label' => ['required', 'string', 'max:200'],
            'field.description' => ['nullable', 'string', 'max:2000'],
            'field.width' => ['sometimes', 'integer', 'between:1,3'],
            'field.min' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'field.max' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'field.options' => ['sometimes', 'array', 'max:100'],
            'field.options.*' => ['string', 'max:200', 'distinct'],
            'field.options_layout' => ['sometimes', 'string', Rule::in(['horizontal', 'vertical'])],
            'field.unit' => ['nullable', 'string', 'max:50'],
            'field.confidentiality' => ['sometimes', Rule::in(['normal', 'confidential'])],
            'field.children' => ['sometimes', 'array', 'max:20'],
            'field.ai' => ['sometimes', 'array:enabled,methods,task,review_required'],
            'field.ai.enabled' => ['sometimes', 'boolean'],
            'field.ai.methods' => ['sometimes', 'array', 'max:5'],
            'field.ai.methods.*' => [Rule::in(['text', 'audio', 'file', 'history', 'ocr'])],
            'field.ai.task' => ['nullable', 'string', 'max:100'],
            'field.ai.review_required' => ['sometimes', 'accepted'],
            ...collect(['required', 'active', 'assistant_editable', 'receptionist_visible', 'exportable'])->mapWithKeys(fn ($key) => ['field.'.$key => ['sometimes', 'boolean']])->all(),
        ])->validate();
        $field = [...['description' => '', 'width' => 1, 'required' => false, 'active' => true, 'default' => null, 'min' => null, 'max' => null, 'options' => [], 'unit' => '', 'condition' => null, 'calculation' => null, 'children' => [], 'confidentiality' => 'normal', 'assistant_editable' => false, 'receptionist_visible' => false, 'exportable' => true, 'ai' => ['enabled' => false, 'methods' => [], 'task' => '', 'review_required' => true]], ...$field];
        $field['description'] ??= '';
        $field['unit'] ??= '';
        if ($field['default'] === '') {
            $field['default'] = null;
        }
        $field['ai'] = [...['enabled' => false, 'methods' => [], 'task' => '', 'review_required' => true], ...$field['ai']];
        $field['ai']['task'] ??= '';
        if ($field['type'] === 'slider') {
            $field['min'] ??= 0;
            $field['max'] ??= 100;
            if (floor($field['min']) != $field['min'] || floor($field['max']) != $field['max']) {
                $this->invalid();
            }
        }
        $kind = (new FieldTypeRegistry)->get($field['type'])['kind'];
        if (($field['min'] !== null && $field['max'] !== null && $field['min'] > $field['max']) || ($kind === 'choice' && count($field['options']) === 0) || ($child && in_array($kind, ['repeater', 'file'], true)) || ($kind !== 'repeater' && count($field['children']) > 0)) {
            $this->invalid();
        }
        if ($kind === 'repeater') {
            if (count($field['children']) === 0) {
                $this->invalid();
            }
            $field['children'] = array_map(fn ($item) => $this->field($item, true), $field['children']);
        }
        if ($child && ($field['condition'] !== null || $field['type'] === 'calculated')) {
            $this->invalid();
        }
        $multiple = in_array($field['type'], ['multi_select', 'checkbox'], true);
        if ($kind === 'boolean' && $field['default'] !== null && ! is_bool($field['default'])) {
            $this->invalid();
        }
        if ($field['default'] !== null && ($multiple ? (! is_array($field['default']) || count(array_filter($field['default'], 'is_string')) !== count($field['default']) || array_diff($field['default'], $field['options'])) : (! is_scalar($field['default']) || mb_strlen((string) $field['default']) > 2000))) {
            $this->invalid();
        }
        if ($field['default'] !== null && $kind === 'number' && (! is_numeric($field['default']) || (in_array($field['type'], ['integer', 'slider'], true) && filter_var($field['default'], FILTER_VALIDATE_INT) === false) || abs((float) $field['default']) > 1e9 || ($field['min'] !== null && $field['default'] < $field['min']) || ($field['max'] !== null && $field['default'] > $field['max']))) {
            $this->invalid();
        }
        if ($kind === 'choice' && ! $multiple && $field['default'] !== null && ! in_array($field['default'], $field['options'], true)) {
            $this->invalid();
        }
        if ($kind === 'text' && (($field['min'] !== null && ($field['min'] < 0 || floor($field['min']) != $field['min'])) || ($field['max'] !== null && ($field['max'] < 0 || floor($field['max']) != $field['max'])))) {
            $this->invalid();
        }

        return $field;
    }

    private function register(array &$fields, array $field): void
    {
        if (isset($fields[$field['key']])) {
            $this->invalid();
        }
        $fields[$field['key']] = $field;
    }

    private function condition(mixed $node, array $fields, int $depth = 0): array
    {
        if (! is_array($node) || $depth > 5 || ! isset($node['op'])) {
            $this->invalid();
        }
        if (in_array($node['op'], ['and', 'or'], true)) {
            if (array_diff(array_keys($node), ['op', 'conditions']) || ! isset($node['conditions']) || ! is_array($node['conditions']) || count($node['conditions']) < 1 || count($node['conditions']) > 10) {
                $this->invalid();
            }
            $dependencies = [];
            foreach ($node['conditions'] as $condition) {
                $dependencies = [...$dependencies, ...$this->condition($condition, $fields, $depth + 1)];
            }

            return $dependencies;
        }
        if (array_diff(array_keys($node), ['op', 'field', 'value']) || ! in_array($node['op'], ['equals', 'in', 'exists', 'gt'], true) || ! is_string($node['field'] ?? null) || ! isset($fields[$node['field']])) {
            $this->invalid();
        }
        $source = $fields[$node['field']];
        if (in_array($source['type'], ['repeater', 'file', 'image', 'multiple_images', 'pdf', 'audio', 'video', 'signature', 'heading', 'description', 'divider'], true)) {
            $this->invalid();
        }
        if ($node['op'] === 'gt' && (! is_numeric($node['value'] ?? null) || (new FieldTypeRegistry)->get($source['type'])['kind'] !== 'number')) {
            $this->invalid();
        }
        if ($node['op'] === 'equals' && ! is_scalar($node['value'] ?? null)) {
            $this->invalid();
        }
        if ($node['op'] === 'in' && (! is_array($node['value'] ?? null) || count($node['value']) > 100 || count(array_filter($node['value'], 'is_scalar')) !== count($node['value']))) {
            $this->invalid();
        }

        return [$node['field']];
    }

    private function expression(mixed $node, array $fields, int $depth = 0): array
    {
        if (! is_array($node) || $depth > 8) {
            $this->invalid();
        }
        if (isset($node['field'])) {
            if (count($node) !== 1 || ! is_string($node['field']) || ! isset($fields[$node['field']]) || (new FieldTypeRegistry)->get($fields[$node['field']]['type'])['kind'] !== 'number') {
                $this->invalid();
            }

            return [$node['field']];
        }
        if (isset($node['number'])) {
            if (count($node) !== 1 || ! is_numeric($node['number']) || abs((float) $node['number']) > 1000000000) {
                $this->invalid();
            }

            return [];
        }
        if (count($node) !== 3 || ! in_array($node['op'] ?? null, ['+', '-', '*', '/'], true)) {
            $this->invalid();
        }

        return [...$this->expression($node['left'] ?? null, $fields, $depth + 1), ...$this->expression($node['right'] ?? null, $fields, $depth + 1)];
    }

    private function cycle(string $key, array $edges, array $path): void
    {
        if (isset($path[$key]) || count($path) > 30) {
            $this->invalid();
        }
        $path[$key] = true;
        foreach ($edges[$key] as $dependency) {
            $this->cycle($dependency, $edges, $path);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['schema' => __('forms.invalid_schema')]);
    }
}
