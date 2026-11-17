<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\DoctorStaffAssignmentRequest;
use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DoctorStaffAssignmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('assignments.manage');

        return view('staff.assignments.index', [
            'assignments' => DoctorStaffAssignment::with(['doctor.user:id,name', 'staff:id,name'])->orderByDesc('id')->paginate(20),
            'doctors' => Doctor::where('active', true)->whereHas('user', fn ($account) => $account->where('active', true))->with('user:id,name')->orderBy('id')->get(),
            'staff' => User::where('active', true)->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['receptionist', 'general-receptionist', 'doctor-assistant']))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(DoctorStaffAssignmentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $staff = User::whereKey($data['staff_user_id'])->lockForUpdate()->firstOrFail();
            $doctor = Doctor::with('user')->findOrFail($data['doctor_id']);
            if (! $staff->active) {
                throw ValidationException::withMessages(['staff_user_id' => __('staff.assignment_staff_inactive')]);
            }
            if (! $doctor->active || ! $doctor->user->active) {
                throw ValidationException::withMessages(['doctor_id' => __('staff.assignment_doctor_inactive')]);
            }
            if ($doctor->user_id === $staff->id) {
                throw ValidationException::withMessages(['staff_user_id' => __('staff.assignment_same_account')]);
            }
            $hasMatchingRole = $data['assignment_type'] === 'assistant'
                ? $staff->hasRole('doctor-assistant')
                : ($staff->hasRole('receptionist') || $staff->hasRole('general-receptionist'));
            if (! $hasMatchingRole) {
                throw ValidationException::withMessages(['staff_user_id' => __('staff.assignment_role_'.$data['assignment_type'])]);
            }

            $startsAt = Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'Asia/Tehran')->startOfMinute()->utc();
            $endsAt = empty($data['ends_at']) ? null : Carbon::createFromFormat('Y-m-d\TH:i', $data['ends_at'], 'Asia/Tehran')->startOfMinute()->utc();
            $overlaps = DoctorStaffAssignment::where('staff_user_id', $staff->id)->where('doctor_id', $doctor->id)->where('assignment_type', $data['assignment_type'])
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))
                ->when($endsAt, fn ($query) => $query->where('starts_at', '<', $endsAt))->exists();
            if ($overlaps) {
                throw ValidationException::withMessages(['starts_at' => __('staff.overlap')]);
            }
            DoctorStaffAssignment::create([...$data, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'assigned_by' => $request->user()->id]);
        });

        return redirect()->route('assignments.index')->with('status', __('staff.saved'));
    }

    public function destroy(DoctorStaffAssignment $assignment): RedirectResponse
    {
        Gate::authorize('assignments.manage');
        DB::transaction(function () use ($assignment): void {
            User::whereKey($assignment->staff_user_id)->lockForUpdate()->firstOrFail();
            $assignment->refresh();
            if ($assignment->ends_at === null || $assignment->ends_at->isFuture()) {
                $assignment->update(['ends_at' => now()]);
            }
        });

        return redirect()->route('assignments.index')->with('status', __('staff.saved'));
    }
}
