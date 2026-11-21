<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\FormVersion;
use App\Services\Forms\FieldTypeRegistry;
use App\Services\Forms\FormAccess;
use App\Services\Forms\FormRuntime;
use App\Services\Forms\ManageFormSubmission;
use App\Services\MedicalRecords\MedicalRecordAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormSubmissionController extends Controller
{
    public function store(Request $request, FormVersion $version, FormAccess $access, FormRuntime $runtime, ManageFormSubmission $submissions): RedirectResponse
    {
        $access->authorize($request->user(), $version->template);
        abort_unless($request->user()->can('forms.test'), 403);
        $data = $request->validate(['values' => ['sometimes', 'array', 'max:200']]);
        $uploads = $request->file('values', []);
        abort_unless(is_array($uploads), 422);
        $submission = DB::transaction(function () use ($request, $version, $access, $runtime, $submissions, $data, $uploads): FormSubmission {
            $template = FormTemplate::whereKey($version->form_template_id)->lockForUpdate()->firstOrFail();
            $currentVersion = $template->versions()->orderByDesc('version_number')->firstOrFail();
            abort_unless($version->id === $currentVersion->id && $version->status === 'published' && $template->active, 403);
            $version = $currentVersion;
            $schema = $access->schemaFor($request->user(), $template, $version->schema_json);
            $values = $runtime->validate($request->user(), $template, $schema, array_diff_key($data['values'] ?? [], $uploads), $uploads);

            return $submissions->create($request->user(), $version, null, $values, $uploads);
        });

        return redirect()->route('form-submissions.show', $submission)->with('status', __('forms.saved'));
    }

    public function show(Request $request, FormSubmission $submission, FormAccess $access, MedicalRecordAccess $records): View
    {
        $encounter = $submission->encounter;
        abort_if($submission->medical_encounter_id !== null && $encounter === null, 404);
        if ($encounter) {
            $records->authorizeEncounter($request->user(), $encounter);
        }
        $version = $submission->version;
        $access->authorize($request->user(), $version->template);
        abort_unless($encounter || $submission->created_by === $request->user()->id || $request->user()->can('forms.manage-all') || $version->template->doctor->user_id === $request->user()->id, 404);
        $submission->load('files');
        $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);
        $allowed = collect($schema['sections'])->flatMap(fn ($section) => $section['fields'])->pluck('key')->all();
        $values = array_intersect_key($submission->values_json, array_flip($allowed));
        $types = app(FieldTypeRegistry::class);
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'repeater' && isset($values[$field['key']])) {
                    $keys = array_flip(array_column($field['children'], 'key'));
                    $values[$field['key']] = array_map(fn ($row) => array_intersect_key($row, $keys), $values[$field['key']]);
                } elseif ($types->get($field['type'])['kind'] === 'file') {
                    $fieldFiles = $submission->files->where('field_key', $field['key'])->pluck('public_id')->values()->all();
                    $values[$field['key']] = $types->acceptsMultipleFiles($field['type']) ? $fieldFiles : ($fieldFiles[0] ?? null);
                }
            }
        }

        $isCurrent = $version->template->versions()->orderByDesc('version_number')->value('id') === $version->id;

        return view('forms.preview', compact('version', 'schema', 'values', 'submission', 'encounter', 'isCurrent'));
    }
}
