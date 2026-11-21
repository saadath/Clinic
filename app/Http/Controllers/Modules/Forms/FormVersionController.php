<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Forms\FormSchemaRequest;
use App\Models\FormVersion;
use App\Services\Forms\FieldTypeRegistry;
use App\Services\Forms\FormAccess;
use App\Services\Forms\ManageForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormVersionController extends Controller
{
    public function edit(Request $request, FormVersion $version, FormAccess $access, FieldTypeRegistry $types): View|RedirectResponse
    {
        $access->authorize($request->user(), $version->template, true);
        $current = $version->template->versions()->orderByDesc('version_number')->firstOrFail();
        if ($current->isNot($version)) {
            return redirect()->route('form-versions.edit', $current);
        }

        $builderTypes = $types->builder();
        $existingTypes = collect($version->schema_json['sections'])->flatMap(fn (array $section) => $section['fields'])->pluck('type')->all();

        return view('forms.editor', [
            'version' => $version,
            'types' => $builderTypes,
            'legacyTypes' => array_intersect_key(array_diff_key($types->all(), $builderTypes), array_flip($existingTypes)),
        ]);
    }

    public function update(FormSchemaRequest $request, FormVersion $version, ManageForm $manager): RedirectResponse
    {
        $data = $request->validated();
        $version = $manager->save($request->user(), $version, $data['schema'], $data['revision']);

        return redirect()->route('form-versions.edit', $version)->with('status', __('forms.saved'));
    }

    public function show(Request $request, FormVersion $version, FormAccess $access): View
    {
        $access->authorize($request->user(), $version->template);
        if ($version->status !== 'published') {
            $access->authorize($request->user(), $version->template, true);
        }
        $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);
        $isCurrent = $version->template->versions()->orderByDesc('version_number')->value('id') === $version->id;

        return view('forms.preview', ['version' => $version, 'schema' => $schema, 'values' => [], 'submission' => null, 'isCurrent' => $isCurrent]);
    }
}
