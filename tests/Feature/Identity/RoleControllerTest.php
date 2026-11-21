<?php

namespace Tests\Feature\Identity;

use App\Models\Role;
use App\Models\User;
use App\Services\Identity\AccessCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function rolePermissions(): array
    {
        return [
            'super' => ['super-admin', true, true], 'admin' => ['clinic-administrator', true, true],
            'doctor' => ['doctor', false, true], 'receptionist' => ['receptionist', false, true],
            'general' => ['general-receptionist', false, true], 'assistant' => ['doctor-assistant', false, true],
            'accountant' => ['accountant', false, false], 'blog-manager' => ['blog-manager', false, false],
            'blog-editor' => ['blog-editor', false, false], 'blog-author' => ['blog-author', false, false], 'patient' => ['patient', false, false],
        ];
    }

    #[DataProvider('rolePermissions')]
    public function test_initial_roles_enforce_permission_matrix(string $name, bool $management, bool $viewDoctors): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $name)->sole());

        foreach (['users.manage', 'roles.manage', 'doctors.manage', 'assignments.manage'] as $ability) {
            $this->assertSame($management, Gate::forUser($user)->allows($ability));
        }
        $this->assertSame($viewDoctors, Gate::forUser($user)->allows('doctors.view'));
        foreach (['patients.view', 'patients.create', 'patients.update'] as $ability) {
            $this->assertSame($viewDoctors, Gate::forUser($user)->allows($ability));
        }
        $this->assertSame(in_array($name, ['super-admin', 'clinic-administrator', 'general-receptionist', 'doctor'], true), Gate::forUser($user)->allows('patients.view-all'));
        $this->assertSame(in_array($name, ['super-admin', 'clinic-administrator', 'general-receptionist'], true), Gate::forUser($user)->allows('doctors.view-all'));
    }

    public function test_custom_role_can_be_created_edited_and_revoked_immediately(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());

        $this->actingAs($admin)->post('/roles', ['name' => 'limited-staff', 'label' => '<script>Role</script>', 'permissions' => ['doctors.view'], 'system' => true])->assertRedirect('/roles');
        $role = Role::where('name', 'limited-staff')->sole();
        $this->assertFalse($role->system);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $this->assertTrue(Gate::forUser($staff)->allows('doctors.view'));
        $this->get('/roles')->assertOk()->assertSee('&lt;script&gt;Role&lt;/script&gt;', false)->assertDontSee('<script>Role</script>', false);
        $this->get('/roles/create')->assertOk();
        $this->get('/roles/'.$role->id.'/edit')->assertOk();
        $this->put('/roles/'.$role->id, ['name' => 'limited-staff', 'label' => 'Limited Staff', 'permissions' => []])->assertRedirect('/roles');
        $this->assertFalse(Gate::forUser($staff)->allows('doctors.view'));
    }

    public function test_only_super_admin_can_edit_system_roles_and_super_admin_keeps_all_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::where('name', 'clinic-administrator')->sole());
        $doctorRole = Role::where('name', 'doctor')->sole();

        $this->assertTrue($doctorRole->permissions()->where('name', 'forms.manage')->exists());
        $this->actingAs($administrator)->get('/roles/'.$doctorRole->id.'/edit')->assertForbidden();
        $this->put('/roles/'.$doctorRole->id, ['name' => 'doctor', 'label' => 'Doctor', 'permissions' => ['doctors.view']])->assertForbidden();

        $this->actingAs($superAdmin)->get('/roles/'.$doctorRole->id.'/edit')->assertOk()
            ->assertSee(__('staff.permission_forms.manage'));
        $this->put('/roles/'.$doctorRole->id, ['name' => 'renamed-doctor', 'label' => 'Changed', 'permissions' => ['doctors.view']])->assertRedirect('/roles');

        $doctorRole->refresh()->load('permissions');
        $this->assertSame('doctor', $doctorRole->name);
        $this->assertSame('Doctor', $doctorRole->label);
        $this->assertSame(['doctors.view'], $doctorRole->permissions->pluck('name')->all());

        $superRole = Role::where('name', 'super-admin')->sole();
        $this->put('/roles/'.$superRole->id, ['name' => 'super-admin', 'label' => 'Super Admin', 'permissions' => []])->assertRedirect('/roles');
        $this->assertSame(count(AccessCatalog::PERMISSIONS), $superRole->permissions()->count());
        $this->assertTrue(Gate::forUser($superAdmin)->allows('roles.manage'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('forms.manage-all'));

        $this->post('/roles', ['name' => 'unknown-role', 'label' => 'Unknown', 'permissions' => ['everything']])->assertSessionHasErrors('permissions.0');
        $this->assertDatabaseMissing('roles', ['name' => 'unknown-role']);
    }

    public function test_staff_cannot_manage_roles_and_inactive_admin_has_no_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create();
        $this->actingAs($staff)->get('/roles')->assertForbidden();
        $this->post('/roles', [])->assertForbidden();
        $staff->roles()->attach(Role::where('name', 'super-admin')->sole());
        $staff->active = false;
        $this->assertFalse(Gate::forUser($staff)->allows('users.manage'));
    }
}
