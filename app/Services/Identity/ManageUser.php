<?php

namespace App\Services\Identity;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageUser
{
    /** @param array{name: string, email: string, mobile?: ?string, password?: ?string, active: mixed, roles: array} $data */
    public function save(User $actor, array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($actor, $data, $user): User {
            Role::where('name', 'super-admin')->lockForUpdate()->firstOrFail();
            $roles = Role::whereIn('id', $data['roles'])->get();
            $isSuper = $actor->hasRole('super-admin');
            abort_if(! $isSuper && ($user?->hasRole('super-admin') || $roles->contains('name', 'super-admin')), 403);
            abort_if(! $actor->hasPermission('roles.manage') && ($user === null || $user->roles()->pluck('roles.id')->sort()->values()->all() !== $roles->pluck('id')->sort()->values()->all()), 403);

            if ($user?->id === $actor->id && (! $data['active'] || $user->roles()->pluck('roles.id')->sort()->values()->all() !== $roles->pluck('id')->sort()->values()->all())) {
                throw ValidationException::withMessages(['roles' => __('staff.self_protection')]);
            }

            $user ??= new User;
            $user->fill(collect($data)->only(['name', 'email'])->all());
            $user->mobile = $data['mobile'] ?? null;
            $user->active = (bool) $data['active'];
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->roles()->sync($roles->pluck('id'));

            return $user;
        });
    }
}
