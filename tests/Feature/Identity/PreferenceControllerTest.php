<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PreferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_preferences_are_saved_only_for_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patchJson('/preferences', [
            'locale' => 'en', 'theme' => 'dark', 'active' => false, 'user_id' => $other->id,
        ])->assertOk()->assertJsonPath('locale', 'en')->assertJsonPath('messages.saved', 'Preferences saved.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'en', 'theme' => 'dark', 'active' => true]);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'locale' => 'fa', 'theme' => 'system']);
        $this->get('/settings')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
    }

    public function test_invalid_preferences_do_not_change_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/preferences', ['locale' => 'xx', 'theme' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['locale', 'theme']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'fa', 'theme' => 'system']);
    }

    public function test_guest_can_choose_login_language_without_an_account(): void
    {
        $this->patchJson('/preferences', ['locale' => 'en', 'theme' => 'light'])
            ->assertOk()->assertSessionHas('locale', 'en');

        $this->get('/login')->assertOk()->assertSee('Welcome to your workspace');
        $this->assertDatabaseCount('users', 0);
    }
}
