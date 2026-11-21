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
        return DB::transaction(function () use ($data, $role): Role {
            $role ??= new Role;

            if (! $role->exists || ! $role->system) {
                $role->fill(['name' => $data['name'], 'label' => $data['label']]);
            }

            $role->save();
            $permissions = $role->name === 'super-admin' ? AccessCatalog::PERMISSIONS : $data['permissions'];
            $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

            return $role;
        });
    }
}
