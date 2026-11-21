<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Forms\FormAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const ROLE_PRIORITY = [
        'super-admin', 'clinic-administrator', 'doctor', 'doctor-assistant',
        'general-receptionist', 'receptionist', 'accountant', 'blog-manager',
        'blog-editor', 'blog-author', 'patient',
    ];

    public function __invoke(Request $request, FormAccess $forms): View
    {
        $user = $request->user();
        $doctor = Doctor::query()->where('user_id', $user->id)->where('active', true)->with('user:id,name')->first();

        return view('dashboard', [
            'doctor' => $doctor,
            'patientCount' => $user->can('patients.view') ? Patient::visibleTo($user)->count() : null,
            'formCount' => $user->can('forms.view') ? $forms->query($user)->where('active', true)->count() : null,
            'roleTitle' => $this->roleTitle($user),
        ]);
    }

    private function roleTitle(User $user): ?string
    {
        $role = $user->roles()
            ->get(['roles.id', 'roles.name', 'roles.label', 'roles.system'])
            ->sortBy(function (Role $role): int {
                $position = array_search($role->name, self::ROLE_PRIORITY, true);

                return $position === false ? count(self::ROLE_PRIORITY) : $position;
            })
            ->first();

        return $role === null ? null : ($role->system ? __('staff.role_'.$role->name) : $role->label);
    }
}