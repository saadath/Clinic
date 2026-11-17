<?php

namespace App\Http\Controllers\Modules\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Identity\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\ManageUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.manage');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'integer', 'exists:roles,id'], 'active' => ['nullable', 'in:0,1']]);
        $users = User::with('roles')->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%')))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($roles) => $roles->where('roles.id', $role)))
            ->when(isset($filters['active']), fn ($query) => $query->where('active', $filters['active']))->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('staff.users.index', ['users' => $users, 'roles' => Role::orderBy('label')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('users.manage');

        return $this->form(new User);
    }

    public function edit(User $user): View
    {
        Gate::authorize('users.manage');
        abort_if($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin'), 403);

        return $this->form($user);
    }

    private function form(User $user): View
    {
        return view('staff.users.form', ['account' => $user->load('roles'), 'roles' => Role::when(! auth()->user()->hasRole('super-admin'), fn ($query) => $query->where('name', '!=', 'super-admin'))->orderBy('label')->get()]);
    }

    public function store(UserRequest $request, ManageUser $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated());

        return redirect()->route('users.index')->with('status', __('staff.saved'));
    }

    public function update(UserRequest $request, User $user, ManageUser $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated(), $user);

        return redirect()->route('users.index')->with('status', __('staff.saved'));
    }
}
