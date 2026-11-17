<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Forms\FormSchemaRequest;
use App\Models\Doctor;
use App\Models\FormVersion;
use App\Services\Forms\FieldTypeRegistry;
use App\Services\Forms\FormAccess;
use App\Services\Forms\ManageForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormVersionController extends Controller
{
    public function edit(Request $request, FormVersion $version, FormAccess $access, FieldTypeRegistry $types): View
    {
        $access->authorize($request->user(), $version->template, true);
        abort_unless($version->status === 'draft', 409);

        return view('forms.editor', ['version' => $version, 'types' => $types->all(), 'doctors' => Doctor::with('user')->where('active', true)->get(), 'shares' => $version->template->recipients()->pluck('doctors.id')->all()]);
    }

    public function update(FormSchemaRequest $request, FormVersion $version, ManageForm $manager): RedirectResponse
    {
        $data = $request->validated();
        $manager->save($request->user(), $version, $data['schema'], $data['revision']);

        return back()->with('status', __('forms.saved'));
    }

    public function publish(Request $request, FormVersion $version, ManageForm $manager, FormAccess $access): RedirectResponse
    {
        $access->authorize($request->user(), $version->template, true);
        $data = $request->validate(['revision' => ['required', 'integer']]);
        $manager->publish($request->user(), $version, $data['revision']);

        return redirect()->route('form-versions.show', $version)->with('status', __('forms.published'));
    }

    public function show(Request $request, FormVersion $version, FormAccess $access): View
    {
        $access->authorize($request->user(), $version->template);
        if ($version->status !== 'published') {
            $access->authorize($request->user(), $version->template, true);
        }
        $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);

        return view('forms.preview', ['version' => $version, 'schema' => $schema, 'values' => [], 'submission' => null]);
    }
}
