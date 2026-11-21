<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\RoleRequest;
use App\Models\Role;
use App\Services\Identity\AccessCatalog;
use App\Services\Identity\ManageRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.manage');

        return view('staff.roles.index', ['roles' => Role::with('permissions')->withCount('users')->orderBy('id')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('roles.manage');

        return view('staff.roles.form', ['role' => new Role, 'permissions' => AccessCatalog::PERMISSIONS]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('roles.manage');
        abort_if($role->system && ! auth()->user()->hasRole('super-admin'), 403);

        return view('staff.roles.form', ['role' => $role->load('permissions'), 'permissions' => AccessCatalog::PERMISSIONS]);
    }

    public function store(RoleRequest $request, ManageRole $manager): RedirectResponse
    {
        $manager->save($request->validated());

        return redirect()->route('roles.index')->with('status', __('staff.saved'));
    }

    public function update(RoleRequest $request, Role $role, ManageRole $manager): RedirectResponse
    {
        abort_if($role->system && ! $request->user()->hasRole('super-admin'), 403);
        $manager->save($request->validated(), $role);

        return redirect()->route('roles.index')->with('status', __('staff.saved'));
    }
}
