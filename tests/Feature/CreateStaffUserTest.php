<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateStaffUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_creates_staff_with_hashed_password(): void
    {
        $this->artisan('clinic:create-user', ['email' => 'staff@example.test', '--name' => 'Clinic Staff'])
            ->expectsQuestion('Password (at least 12 characters)', 'A-long-password-123')
            ->expectsQuestion('Confirm password', 'A-long-password-123')
            ->assertSuccessful();

        $user = User::where('email', 'staff@example.test')->sole();
        $this->assertTrue(Hash::check('A-long-password-123', $user->password));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Clinic Staff', 'active' => true]);
    }

    public function test_command_rejects_short_password_without_creating_account(): void
    {
        $this->artisan('clinic:create-user', ['email' => 'staff@example.test', '--name' => 'Clinic Staff'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
