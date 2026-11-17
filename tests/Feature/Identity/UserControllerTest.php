<?php

namespace Tests\Feature\Identity;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function administrator(string $role = 'super-admin'): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    public function test_guests_and_unprivileged_staff_cannot_manage_accounts(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/users')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->put('/users/'.$user->id, [])->assertForbidden();
    }

    public function test_administrator_creates_and_updates_staff_with_roles_and_hashed_password(): void
    {
        $admin = $this->administrator();
        $role = Role::where('name', 'receptionist')->sole();
        $data = ['name' => '<script>Staff</script>', 'email' => 'STAFF@example.test', 'mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'active' => '1', 'password' => 'Long-password-123', 'password_confirmation' => 'Long-password-123', 'roles' => [$role->id], 'system' => true];

        $this->actingAs($admin)->post('/users', $data)->assertRedirect('/users');

        $staff = User::where('email', 'staff@example.test')->sole();
        $this->assertTrue(Hash::check('Long-password-123', $staff->password));
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'mobile' => '09123456789', 'active' => true]);
        $this->assertDatabaseHas('role_user', ['user_id' => $staff->id, 'role_id' => $role->id]);
        $this->get('/users?q=Staff')->assertOk()->assertSee('&lt;script&gt;Staff&lt;/script&gt;', false)->assertDontSee('<script>Staff</script>', false);
        $this->get('/users/create')->assertOk();
        $this->get('/users/'.$staff->id.'/edit')->assertOk();

        $this->put('/users/'.$staff->id, [...$data, 'name' => 'Updated Staff', 'active' => '0', 'password' => '', 'password_confirmation' => ''])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'name' => 'Updated Staff', 'active' => false]);
        $this->assertTrue(Hash::check('Long-password-123', $staff->fresh()->password));
    }

    public function test_clinic_administrator_cannot_grant_super_admin_or_modify_super_admin_account(): void
    {
        $admin = $this->administrator('clinic-administrator');
        $super = Role::where('name', 'super-admin')->sole();
        $target = User::factory()->create();
        $data = ['name' => 'Changed', 'email' => $target->email, 'active' => '1', 'roles' => [$super->id]];

        $this->actingAs($admin)->put('/users/'.$target->id, $data)->assertForbidden();
        $this->assertDatabaseMissing('role_user', ['user_id' => $target->id, 'role_id' => $super->id]);
        $target->roles()->attach($super);
        $this->get('/users/'.$target->id.'/edit')->assertForbidden();
        $this->put('/users/'.$target->id, [...$data, 'roles' => []])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
    }

    public function test_administrator_cannot_deactivate_or_remove_own_roles(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'active' => '0', 'roles' => []])
            ->assertSessionHasErrors(['roles' => 'برای حفظ دسترسی، نقش‌های خود را تغییر ندهید و حساب خود را غیرفعال نکنید.']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'active' => true]);
        $this->assertTrue($admin->hasRole('super-admin'));
    }

    public function test_invalid_account_payload_does_not_create_account(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post('/users', ['name' => 'Staff', 'email' => $admin->email, 'mobile' => '123', 'active' => '1', 'password' => 'short', 'password_confirmation' => 'different', 'roles' => [99999]])
            ->assertSessionHasErrors(['email', 'mobile', 'password', 'roles.0']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_manager_without_role_permission_cannot_assign_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::where('name', 'users.manage')->sole());
        $manager = User::factory()->create();
        $manager->roles()->attach($role);
        $target = User::factory()->create();

        $this->actingAs($manager)->put('/users/'.$target->id, ['name' => 'Changed', 'email' => $target->email, 'active' => '1', 'roles' => [$role->id]])->assertForbidden();
        $this->assertDatabaseMissing('role_user', ['user_id' => $target->id, 'role_id' => $role->id]);
    }
}
