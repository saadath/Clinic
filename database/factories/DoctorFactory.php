<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'medical_council_number' => fake()->unique()->numerify('########'),
            'specialty' => 'Internal Medicine',
            'visit_duration_minutes' => 20,
            'visit_price_rial' => 1000000,
            'active' => true,
        ];
    }
}
