<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorStaffAssignment>
 */
class DoctorStaffAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'staff_user_id' => User::factory(),
            'assignment_type' => 'receptionist',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'assigned_by' => User::factory(),
        ];
    }
}
