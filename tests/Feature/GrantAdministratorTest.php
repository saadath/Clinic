<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GrantAdministratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_console_grants_admin_to_existing_account_and_can_be_repeated(): void
    {
        $account = User::factory()->create();

        $this->artisan('clinic:grant-admin', ['email' => $account->email])->assertSuccessful();
        $this->artisan('clinic:grant-admin', ['email' => $account->email])->assertSuccessful();

        $this->assertTrue($account->hasRole('super-admin'));
        $this->assertDatabaseCount('role_user', 1);
        $this->assertDatabaseCount('permissions', 19);
        $this->assertDatabaseCount('roles', 11);
    }

    public function test_console_does_not_create_or_enable_accounts(): void
    {
        $account = User::factory()->create(['active' => false]);

        $this->artisan('clinic:grant-admin', ['email' => $account->email])->assertFailed();
        $this->artisan('clinic:grant-admin', ['email' => 'missing@example.test'])->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('role_user', 0);
    }
}
