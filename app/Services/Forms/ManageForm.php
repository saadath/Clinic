<?php

namespace App\Services\Forms;

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
            $template->versions()->create(['version_number' => 1, 'status' => 'draft', 'revision' => 1, 'schema_json' => $schema]);

            return $template;
        });
    }

    public function draft(User $actor, FormTemplate $template): FormVersion
    {
        $this->access->authorize($actor, $template, true);

        return DB::transaction(function () use ($template): FormVersion {
            FormTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $latest = $template->versions()->orderByDesc('version_number')->firstOrFail();
            if ($latest->status === 'draft') {
                return $latest;
            }

            return $template->versions()->create(['version_number' => $latest->version_number + 1, 'status' => 'draft', 'revision' => 1, 'schema_json' => $latest->schema_json]);
        });
    }

    public function save(User $actor, FormVersion $version, array $schema, int $revision): void
    {
        $this->access->authorize($actor, $version->template, true);
        $schema = $this->validator->validate($schema);
        DB::transaction(function () use ($version, $schema, $revision): void {
            $version = FormVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_if($version->status !== 'draft' || $version->revision !== $revision, 409, __('forms.conflict'));
            $version->update(['schema_json' => $schema, 'revision' => $revision + 1]);
        });
    }

    public function publish(User $actor, FormVersion $version, int $revision): void
    {
        $this->access->authorize($actor, $version->template, true);
        DB::transaction(function () use ($actor, $version, $revision): void {
            $version = FormVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_if($version->status !== 'draft' || $version->revision !== $revision, 409, __('forms.conflict'));
            $schema = $this->validator->validate($version->schema_json);
            if (collect($schema['sections'])->sum(fn ($section) => count($section['fields'])) === 0) {
                throw ValidationException::withMessages(['schema' => __('forms.empty_publish')]);
            }
            $version->update(['schema_json' => $schema, 'schema_hash' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)), 'status' => 'published', 'published_at' => now(), 'published_by' => $actor->id]);
        });
    }
}
