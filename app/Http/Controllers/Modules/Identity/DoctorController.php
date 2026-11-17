<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\DoctorRequest;
use App\Models\Doctor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DoctorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('doctors.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $doctors = Doctor::visibleTo($request->user())->with('user:id,name')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('specialty', 'like', '%'.$search.'%')->orWhereHas('user', fn ($account) => $account->where('name', 'like', '%'.$search.'%'))))
            ->orderBy('id')->paginate(15)->withQueryString();

        return view('staff.doctors.index', ['doctors' => $doctors]);
    }

    public function show(Request $request, Doctor $doctor): View
    {
        Gate::authorize('doctors.view');
        abort_unless(Doctor::visibleTo($request->user())->whereKey($doctor->id)->exists(), 404);

        return view('staff.doctors.show', ['doctor' => $doctor->load('user:id,name')]);
    }

    public function create(): View
    {
        Gate::authorize('doctors.manage');

        return view('staff.doctors.form', ['doctor' => new Doctor, 'accounts' => User::where('active', true)->whereDoesntHave('doctor')->orderBy('name')->get(['id', 'name'])]);
    }

    public function edit(Doctor $doctor): View
    {
        Gate::authorize('doctors.manage');

        return view('staff.doctors.form', ['doctor' => $doctor, 'accounts' => User::whereKey($doctor->user_id)->get(['id', 'name'])]);
    }

    public function store(DoctorRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $account = User::whereKey($request->integer('user_id'))->lockForUpdate()->firstOrFail();
            Doctor::create($request->validated());
            $account->roles()->syncWithoutDetaching([Role::where('name', 'doctor')->sole()->id]);
        });

        return redirect()->route('doctors.index')->with('status', __('staff.saved'));
    }

    public function update(DoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        abort_unless($doctor->user_id === $request->integer('user_id'), 422);
        $doctor->update($request->safe()->except('user_id'));

        return redirect()->route('doctors.index')->with('status', __('staff.saved'));
    }
}
