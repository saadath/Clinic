<?php

namespace App\Http\Controllers\Modules\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Patients\PatientRequest;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\Patients\PatientInput;
use App\Services\Patients\SavePatient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('patients.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = PatientInput::digits($filters['q'] ?? '');
        $patients = Patient::visibleTo($request->user())->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('national_id', $search)->orWhere('mobile', $search)))
            ->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(15)->withQueryString();

        return view('patients.index', compact('patients'));
    }

    public function create(): View
    {
        Gate::authorize('patients.create');

        return $this->form(new Patient);
    }

    public function edit(Request $request, Patient $patient): View
    {
        Gate::authorize('patients.update');
        $this->ensureVisible($request, $patient);

        return $this->form($patient);
    }

    private function form(Patient $patient): View
    {
        $nationalId = session()->getOldInput('national_id');
        $mobile = session()->getOldInput('mobile');
        $duplicates = Patient::visibleTo(auth()->user())
            ->where(function ($query) use ($nationalId, $mobile): void {
                $query->whereRaw('1 = 0');
                if (is_string($nationalId) && $nationalId !== '') {
                    $query->orWhere('national_id', PatientInput::digits($nationalId));
                }
                if (is_string($mobile) && $mobile !== '') {
                    $query->orWhere('mobile', PatientInput::digits($mobile));
                }
            })->when($patient->exists, fn ($query) => $query->whereKeyNot($patient->id))->limit(5)->get();

        return view('patients.form', ['patient' => $patient, 'duplicates' => $duplicates, 'doctors' => Doctor::visibleTo(auth()->user())->where('active', true)->with('user:id,name')->get()]);
    }

    private function ensureVisible(Request $request, Patient $patient): void
    {
        abort_unless(Patient::visibleTo($request->user())->whereKey($patient->id)->exists(), 404);
    }

    public function show(Request $request, Patient $patient): View
    {
        Gate::authorize('patients.view');
        $this->ensureVisible($request, $patient);

        return view('patients.show', ['patient' => $patient]);
    }

    public function store(PatientRequest $request, SavePatient $save): RedirectResponse
    {
        $patient = $save->save($request->user(), $request->validated());

        return redirect()->route('patients.show', $patient)->with('status', __('patients.saved'));
    }

    public function update(PatientRequest $request, Patient $patient, SavePatient $save): RedirectResponse
    {
        $this->ensureVisible($request, $patient);
        $save->save($request->user(), $request->validated(), $patient);

        return redirect()->route('patients.show', $patient)->with('status', __('patients.saved'));
    }
}
