<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\FormTemplate;
use App\Services\Forms\FormAccess;
use App\Services\Forms\ManageForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FormTemplateController extends Controller
{
    public function index(Request $request, FormAccess $access): View
    {
        Gate::authorize('forms.view');
        $shared = FormTemplate::whereHas('recipients', fn ($query) => $query->where('user_id', $request->user()->id))->with('doctor.user')->get();

        return view('forms.index', ['templates' => $access->query($request->user())->with(['doctor.user', 'versions' => fn ($query) => $query->orderByDesc('version_number')])->orderByDesc('id')->paginate(15), 'shared' => $shared]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('forms.manage');

        return view('forms.create', ['doctors' => Doctor::where('active', true)->when(! $request->user()->can('forms.manage-all'), fn ($query) => $query->where('user_id', $request->user()->id))->with('user')->get()]);
    }

    public function store(Request $request, ManageForm $manager): RedirectResponse
    {
        Gate::authorize('forms.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'doctor_id' => ['required', 'integer']]);
        $template = $manager->create($request->user(), $data['doctor_id'], $data['name']);

        return redirect()->route('forms.edit', $template);
    }

    public function edit(Request $request, FormTemplate $template, ManageForm $manager): RedirectResponse
    {
        $version = $manager->draft($request->user(), $template);

        return redirect()->route('form-versions.edit', $version);
    }

    public function update(Request $request, FormTemplate $template, FormAccess $access): RedirectResponse
    {
        $access->authorize($request->user(), $template, true);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'active' => ['required', 'boolean']]);
        $template->update($data);
        if (! $template->active) {
            DB::table('doctor_form_defaults')->where('form_template_id', $template->id)->delete();
        }

        return back()->with('status', __('forms.saved'));
    }

    public function copy(Request $request, FormTemplate $template, FormAccess $access, ManageForm $manager): RedirectResponse
    {
        Gate::authorize('forms.manage');
        $owned = $access->query($request->user())->whereKey($template->id)->exists();
        $shared = $template->recipients()->where('user_id', $request->user()->id)->exists();
        abort_unless($owned || $shared, 404);
        $data = $request->validate(['doctor_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255']]);
        $version = $template->versions()->where('status', 'published')->orderByDesc('version_number')->firstOrFail();
        $schema = $version->schema_json;
        foreach ($schema['sections'] as &$section) {
            foreach ($section['fields'] as &$field) {
                $field['ai'] = ['enabled' => false, 'methods' => [], 'task' => '', 'review_required' => true];
                foreach ($field['children'] as &$child) {
                    $child['ai'] = $field['ai'];
                }
            }
        }
        $copy = $manager->create($request->user(), $data['doctor_id'], $data['name'], $schema);

        return redirect()->route('forms.edit', $copy);
    }

    public function share(Request $request, FormTemplate $template, FormAccess $access): RedirectResponse
    {
        $access->authorize($request->user(), $template, true);
        $data = $request->validate(['doctor_id' => ['required', 'integer', 'exists:doctors,id'], 'revoke' => ['sometimes', 'boolean']]);
        abort_if((int) $data['doctor_id'] === $template->owner_doctor_id, 422);
        if ($request->boolean('revoke')) {
            $template->recipients()->detach($data['doctor_id']);
        } elseif (! $template->recipients()->whereKey($data['doctor_id'])->exists()) {
            $template->recipients()->attach($data['doctor_id'], ['granted_by' => $request->user()->id]);
        }

        return back()->with('status', __('forms.saved'));
    }

    public function default(Request $request, FormTemplate $template, FormAccess $access): RedirectResponse
    {
        $access->authorize($request->user(), $template, true);
        $data = $request->validate(['purpose' => ['required', 'in:encounter,history,examination']]);
        abort_unless($template->active && $template->versions()->where('status', 'published')->exists(), 422);
        DB::table('doctor_form_defaults')->updateOrInsert(['doctor_id' => $template->owner_doctor_id, 'form_purpose' => $data['purpose']], ['form_template_id' => $template->id]);

        return back()->with('status', __('forms.saved'));
    }
}
