<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Services\Forms\FormAccess;
use App\Services\Forms\FormRuntime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function store(Request $request, FormVersion $version, FormAccess $access, FormRuntime $runtime): RedirectResponse
    {
        $access->authorize($request->user(), $version->template);
        abort_unless($request->user()->can('forms.test') && $version->status === 'published' && $version->template->active, 403);
        $data = $request->validate(['values' => ['sometimes', 'array', 'max:200']]);
        $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);
        $values = $runtime->validate($request->user(), $version->template, $schema, $data['values'] ?? []);
        $submission = FormSubmission::create(['form_version_id' => $version->id, 'values_json' => $values, 'created_by' => $request->user()->id]);

        return redirect()->route('form-submissions.show', $submission)->with('status', __('forms.saved'));
    }

    public function show(Request $request, FormSubmission $submission, FormAccess $access): View
    {
        $version = $submission->version;
        $access->authorize($request->user(), $version->template);
        abort_unless($submission->created_by === $request->user()->id || $request->user()->can('forms.manage-all') || $version->template->doctor->user_id === $request->user()->id, 404);
        $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);
        $allowed = collect($schema['sections'])->flatMap(fn ($section) => $section['fields'])->pluck('key')->all();
        $values = array_intersect_key($submission->values_json, array_flip($allowed));
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'repeater' && isset($values[$field['key']])) {
                    $keys = array_flip(array_column($field['children'], 'key'));
                    $values[$field['key']] = array_map(fn ($row) => array_intersect_key($row, $keys), $values[$field['key']]);
                }
            }
        }

        return view('forms.preview', compact('version', 'schema', 'values', 'submission'));
    }
}
