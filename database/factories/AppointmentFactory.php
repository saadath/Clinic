<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'patient_id' => Patient::factory(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(20),
            'status' => 'scheduled',
            'revision' => 1,
            'visit_type' => 'consultation',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
