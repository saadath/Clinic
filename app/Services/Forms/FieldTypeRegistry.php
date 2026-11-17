<?php

namespace App\Services\Forms;

class FieldTypeRegistry
{
    /** @return array<string, array{input: string, kind: string}> */
    public function all(): array
    {
        $types = [];
        foreach (['short_text' => 'text', 'long_text' => 'textarea', 'rich_text' => 'textarea', 'email' => 'email', 'phone' => 'tel', 'url' => 'url', 'date' => 'date', 'datetime' => 'datetime-local', 'time' => 'time', 'color' => 'color', 'hidden' => 'text'] as $type => $input) {
            $types[$type] = ['input' => $input, 'kind' => 'text'];
        }
        foreach (['integer', 'decimal', 'number_range', 'slider', 'medical_unit', 'calculated'] as $type) {
            $types[$type] = ['input' => $type === 'slider' ? 'range' : 'number', 'kind' => 'number'];
        }
        foreach (['select', 'radio', 'checkbox', 'multi_select'] as $type) {
            $types[$type] = ['input' => 'select', 'kind' => 'choice'];
        }
        foreach (['heading', 'description', 'divider'] as $type) {
            $types[$type] = ['input' => 'display', 'kind' => 'display'];
        }
        $types['repeater'] = ['input' => 'repeater', 'kind' => 'repeater'];
        foreach (['file', 'image', 'multiple_images', 'pdf', 'audio', 'video', 'signature'] as $type) {
            $types[$type] = ['input' => 'file', 'kind' => 'file'];
        }

        return $types;
    }

    public function get(string $type): array
    {
        return $this->all()[$type];
    }
}
