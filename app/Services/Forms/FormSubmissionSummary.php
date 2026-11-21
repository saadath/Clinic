<?php

namespace App\Services\Forms;

use App\Models\FormSubmission;
use App\Models\FormSubmissionFile;
use App\Models\User;

class FormSubmissionSummary
{
    public function __construct(private FormAccess $access, private FieldTypeRegistry $types) {}

    /** @return list<array{label: string, text: ?string, files: list<FormSubmissionFile>}> */
    public function fields(User $user, FormSubmission $submission, bool $forExport = false): array
    {
        $version = $submission->version;
        $schema = $this->access->schemaFor($user, $version->template, $version->schema_json);
        $values = $submission->values_json;
        $files = $forExport ? collect() : $submission->files->groupBy('field_key');
        $summary = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $value = $values[$field['key']] ?? null;
                if (! $field['active'] || $value === null || $value === '' || $value === [] || $field['type'] === 'hidden') {
                    continue;
                }

                $kind = $this->types->get($field['type'])['kind'];
                if ($forExport && ($kind === 'file' || ! ($field['exportable'] ?? true))) {
                    continue;
                }
                $attachments = [];
                $text = null;
                if ($kind === 'file') {
                    $byId = $files->get($field['key'], collect())->keyBy('public_id');
                    foreach ((array) $value as $id) {
                        if (is_string($id) && ($file = $byId->get($id)) !== null) {
                            $attachments[] = $file;
                        }
                    }
                } elseif ($kind !== 'display') {
                    $text = $this->format($field, $value, $forExport);
                }

                if ($text !== null || $attachments !== []) {
                    $summary[] = ['label' => $field['label'], 'text' => $text, 'files' => $attachments];
                }
            }
        }

        return $summary;
    }

    private function format(array $field, mixed $value, bool $forExport): ?string
    {
        if ($value === null || $value === '' || $value === [] || $field['type'] === 'hidden') {
            return null;
        }
        if ($field['type'] === 'single_checkbox') {
            return in_array($value, [true, 1, '1'], true) ? __('medical_records.answer_checked') : __('medical_records.answer_unchecked');
        }
        if ($field['type'] === 'repeater' && is_array($value)) {
            $rows = [];
            foreach ($value as $row) {
                $cells = [];
                foreach ($field['children'] as $child) {
                    if ($child['active'] && (! $forExport || ($child['exportable'] ?? true)) && ($text = $this->format($child, $row[$child['key']] ?? null, $forExport)) !== null) {
                        $cells[] = $child['label'].': '.$text;
                    }
                }
                if ($cells !== []) {
                    $rows[] = implode(' | ', $cells);
                }
            }

            return $rows === [] ? null : implode("\n", $rows);
        }
        if (in_array($field['type'], ['checkbox', 'multi_select'], true) && is_array($value)) {
            $choices = array_filter($value, 'is_string');

            return $choices === [] ? null : implode(' - ', $choices);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $unit = $field['type'] !== 'slider' && $this->types->get($field['type'])['kind'] === 'number' ? ($field['unit'] ?? '') : '';

        return (string) $value.($unit !== '' ? ' '.$unit : '');
    }
}
