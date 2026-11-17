<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->unique()->numerify('0########');
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += (int) $prefix[$index] * (10 - $index);
        }
        $remainder = $sum % 11;

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'national_id' => $prefix.($remainder < 2 ? $remainder : 11 - $remainder),
            'mobile' => fake()->numerify('091########'),
            'birth_date' => '1990-03-21',
            'sex' => 'female',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
