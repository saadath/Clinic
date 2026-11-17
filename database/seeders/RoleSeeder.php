<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Identity\AccessCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (AccessCatalog::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        foreach (AccessCatalog::roles() as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $definition['label'], 'system' => true]);
            $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $definition['permissions'])->pluck('id'));
        }
    }
}
