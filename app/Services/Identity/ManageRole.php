<?php

namespace App\Services\Identity;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class ManageRole
{
    /** @param array{name: string, label: string, permissions: array} $data */
    public function save(array $data, ?Role $role = null): Role
    {
        abort_if($role?->system, 403);

        return DB::transaction(function () use ($data, $role): Role {
            $role ??= new Role;
            $role->fill(['name' => $data['name'], 'label' => $data['label']]);
            $role->save();
            $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));

            return $role;
        });
    }
}
