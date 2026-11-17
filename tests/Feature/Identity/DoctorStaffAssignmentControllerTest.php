<?php

namespace Tests\Feature\Identity;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DoctorStaffAssignmentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assignments_support_multiple_doctors_and_can_be_ended_without_deleting_history(): void
    {
        $this->freezeTime();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'receptionist')->sole());
        $doctors = Doctor::factory()->count(2)->create();
        foreach ($doctors as $doctor) {
            $doctor->user->roles()->attach(Role::where('name', 'doctor')->sole());
            $this->actingAs($admin)->post('/assignments', ['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'assignment_type' => 'receptionist', 'starts_at' => now('Asia/Tehran')->subDay()->format('Y-m-d\TH:i')])->assertRedirect('/assignments');
        }
        $this->assertDatabaseCount('doctor_staff_assignments', 2);
        $this->get('/assignments')->assertOk()->assertSee($staff->name);
        $assignment = DoctorStaffAssignment::where('doctor_id', $doctors[0]->id)->sole();
        $this->actingAs($staff)->get('/doctors/'.$doctors[0]->id)->assertOk();
        $this->actingAs($admin)->delete('/assignments/'.$assignment->id)->assertRedirect('/assignments');
        $this->assertModelExists($assignment);
        $this->assertNotNull($assignment->fresh()->ends_at);
        $this->actingAs($staff)->get('/doctors/'.$doctors[0]->id)->assertNotFound();
        $this->get('/doctors/'.$doctors[1]->id)->assertOk();
    }

    public function test_invalid_role_and_overlapping_assignments_are_rejected(): void
    {
        $this->freezeTime();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $staff = User::factory()->create();
        $doctor = Doctor::factory()->create();
        $data = ['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'assignment_type' => 'assistant', 'starts_at' => now('Asia/Tehran')->format('Y-m-d\TH:i')];

        $this->actingAs($admin)->post('/assignments', $data)->assertSessionHasErrors('staff_user_id');
        $this->assertDatabaseCount('doctor_staff_assignments', 0);
        $staff->roles()->attach(Role::where('name', 'doctor-assistant')->sole());
        $this->post('/assignments', $data)->assertRedirect('/assignments');
        $this->post('/assignments', $data)->assertSessionHasErrors('starts_at');
        $this->post('/assignments', [...$data, 'ends_at' => now('Asia/Tehran')->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('ends_at');
        $this->assertDatabaseCount('doctor_staff_assignments', 1);
    }

    public function test_staff_cannot_create_or_end_assignments(): void
    {
        $staff = User::factory()->create();
        $assignment = DoctorStaffAssignment::factory()->create();

        $this->actingAs($staff)->get('/assignments')->assertForbidden();
        $this->post('/assignments', [])->assertForbidden();
        $this->delete('/assignments/'.$assignment->id)->assertForbidden();
        $this->assertNull($assignment->fresh()->ends_at);
    }
}
