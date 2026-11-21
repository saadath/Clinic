<?php

namespace App\Services\Forms;

use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageForm
{
    public function __construct(private ValidateFormSchema $validator, private FormAccess $access) {}

    public function create(User $actor, int $doctorId, string $name, ?array $schema = null): FormTemplate
    {
        $this->access->owner($actor, $doctorId);
        $schema = $this->validator->validate($schema ?? ['sections' => [['key' => 'main', 'title' => __('forms.main'), 'columns' => 2, 'fields' => []]]]);

        return DB::transaction(function () use ($actor, $doctorId, $name, $schema): FormTemplate {
            $template = new FormTemplate(['name' => $name]);
            $template->owner_doctor_id = $doctorId;
            $template->created_by = $actor->id;
            $template->save();
            $hasFields = collect($schema['sections'])->sum(fn ($section) => count($section['fields'])) > 0;
            $template->versions()->create([
                'version_number' => 1,
                'status' => $hasFields ? 'published' : 'draft',
                'revision' => 1,
                'schema_json' => $schema,
                'schema_hash' => $hasFields ? hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)) : null,
                'published_at' => $hasFields ? now() : null,
                'published_by' => $hasFields ? $actor->id : null,
            ]);

            return $template;
        });
    }

    public function delete(User $actor, FormTemplate $template): bool
    {
        $this->access->authorize($actor, $template, true);

        return DB::transaction(function () use ($template): bool {
            $template = FormTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();

            if (FormSubmission::query()->whereIn('form_version_id', $template->versions()->select('id'))->exists()) {
                return false;
            }

            DB::table('doctor_form_defaults')->where('form_template_id', $template->id)->delete();
            DB::table('form_template_shares')->where('form_template_id', $template->id)->delete();
            $template->versions()->delete();
            $template->delete();

            return true;
        });
    }

    public function current(User $actor, FormTemplate $template): FormVersion
    {
        $this->access->authorize($actor, $template, true);

        return $template->versions()->orderByDesc('version_number')->firstOrFail();
    }

    public function save(User $actor, FormVersion $version, array $schema, int $revision): FormVersion
    {
        $this->access->authorize($actor, $version->template, true);
        $schema = $this->validator->validate($schema);
        $hasFields = collect($schema['sections'])->sum(fn ($section) => count($section['fields'])) > 0;

        return DB::transaction(function () use ($actor, $version, $schema, $revision, $hasFields): FormVersion {
            $template = FormTemplate::whereKey($version->form_template_id)->lockForUpdate()->firstOrFail();
            $current = $template->versions()->orderByDesc('version_number')->firstOrFail();
            abort_if($current->id !== $version->id, 409, __('forms.conflict'));
            $version = FormVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_if($version->revision !== $revision, 409, __('forms.conflict'));
            $this->ensureNoNewLegacyFields($schema, $version->schema_json);

            $attributes = ['schema_json' => $schema, 'revision' => $revision + 1];
            if ($hasFields) {
                $attributes += [
                    'schema_hash' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)),
                    'status' => 'published',
                    'published_at' => now(),
                    'published_by' => $actor->id,
                ];
            }

            if ($version->status === 'published' && $version->submissions()->exists()) {
                if (! $hasFields) {
                    DB::table('doctor_form_defaults')->where('form_template_id', $template->id)->delete();
                }

                return $template->versions()->create([
                    'version_number' => $version->version_number + 1,
                    'status' => $hasFields ? 'published' : 'draft',
                    'revision' => 1,
                    'schema_json' => $schema,
                    'schema_hash' => $hasFields ? hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)) : null,
                    'published_at' => $hasFields ? now() : null,
                    'published_by' => $hasFields ? $actor->id : null,
                ]);
            }

            if (! $hasFields) {
                $attributes += ['status' => 'draft', 'schema_hash' => null, 'published_at' => null, 'published_by' => null];
            }
            $version->update($attributes);

            if (! $hasFields) {
                DB::table('doctor_form_defaults')->where('form_template_id', $template->id)->delete();
            }

            return $version;
        });
    }

    private function ensureNoNewLegacyFields(array $schema, array $previousSchema): void
    {
        $fields = static fn (array $schema) => collect($schema['sections'])
            ->flatMap(fn (array $section) => $section['fields'])
            ->flatMap(fn (array $field) => [$field, ...$field['children']]);
        $previousTypes = $fields($previousSchema)->pluck('type', 'key');
        $availableTypes = (new FieldTypeRegistry)->builder();

        foreach ($fields($schema) as $field) {
            if (! isset($availableTypes[$field['type']]) && $previousTypes->get($field['key']) !== $field['type']) {
                throw ValidationException::withMessages(['schema' => __('forms.invalid_schema')]);
            }
        }
    }
}
