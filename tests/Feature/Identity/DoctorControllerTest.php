<?php

namespace Tests\Feature\Identity;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_doctor_only_sees_own_profile_and_general_receptionist_sees_all(): void
    {
        $this->seed(RoleSeeder::class);
        $doctor = Doctor::factory()->create();
        $doctor->user->roles()->attach(Role::where('name', 'doctor')->sole());
        $other = Doctor::factory()->create();

        $this->actingAs($doctor->user)->get('/doctors')->assertOk()->assertSee($doctor->user->name)->assertDontSee($other->user->name);
        $this->get('/doctors/'.$other->id)->assertNotFound();
        $this->get('/doctors/'.$doctor->id)->assertOk();
        $this->get('/doctors/create')->assertForbidden();
        $this->put('/doctors/'.$doctor->id, [])->assertForbidden();

        $general = User::factory()->create();
        $general->roles()->attach(Role::where('name', 'general-receptionist')->sole());
        $this->actingAs($general)->get('/doctors/'.$other->id)->assertOk();
    }

    public function test_staff_access_expires_and_role_removal_revokes_current_assignment(): void
    {
        $this->freezeTime();
        $this->seed(RoleSeeder::class);
        $doctor = Doctor::factory()->create();
        $doctor->user->roles()->attach(Role::where('name', 'doctor')->sole());
        $staff = User::factory()->create();
        $role = Role::where('name', 'receptionist')->sole();
        $staff->roles()->attach($role);
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'ends_at' => now()->addHour()]);

        $this->actingAs($staff)->get('/doctors/'.$doctor->id)->assertOk();
        $this->travel(1)->hours();
        $this->get('/doctors/'.$doctor->id)->assertNotFound();
        $this->travelBack();
        $staff->roles()->detach($role);
        $staff->roles()->attach(Role::where('name', 'doctor')->sole());
        $this->get('/doctors/'.$doctor->id)->assertNotFound();
    }

    public function test_administrator_creates_doctor_and_updates_profile(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $account = User::factory()->create();
        $data = ['user_id' => $account->id, 'medical_council_number' => '12345', 'specialty' => 'Internal Medicine', 'bio' => '<script>Biography</script>', 'visit_duration_minutes' => 20, 'visit_price_rial' => 5000000, 'active' => '1'];

        $this->actingAs($admin)->get('/doctors/create')->assertOk();
        $this->post('/doctors', $data)->assertRedirect('/doctors');
        $doctor = Doctor::where('user_id', $account->id)->sole();
        $this->assertTrue($account->hasRole('doctor'));
        $this->get('/doctors/'.$doctor->id)->assertOk()->assertSee('&lt;script&gt;Biography&lt;/script&gt;', false)->assertDontSee('<script>Biography</script>', false);
        $this->get('/doctors/'.$doctor->id.'/edit')->assertOk();
        $this->put('/doctors/'.$doctor->id, [...$data, 'specialty' => 'Cardiology', 'active' => '0'])->assertRedirect('/doctors');
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'specialty' => 'Cardiology', 'active' => false]);
        $this->post('/doctors', $data)->assertSessionHasErrors(['user_id', 'medical_council_number']);
    }

    public function test_future_assignment_and_inactive_doctor_do_not_grant_access(): void
    {
        $this->freezeTime();
        $this->seed(RoleSeeder::class);
        $doctor = Doctor::factory()->create();
        $doctor->user->roles()->attach(Role::where('name', 'doctor')->sole());
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'doctor-assistant')->sole());
        $assignment = DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'assignment_type' => 'assistant', 'starts_at' => now()->addHour()]);

        $this->actingAs($staff)->get('/doctors/'.$doctor->id)->assertNotFound();
        $assignment->update(['starts_at' => now()->subHour()]);
        $this->get('/doctors/'.$doctor->id)->assertOk();
        $doctor->update(['active' => false]);
        $this->get('/doctors/'.$doctor->id)->assertNotFound();
        $doctor->update(['active' => true]);
        $doctor->user->active = false;
        $doctor->user->save();
        $this->get('/doctors/'.$doctor->id)->assertNotFound();
    }
}
