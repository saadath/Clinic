<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_see_login_but_cannot_access_workspace(): void
    {
        $this->get('/login')->assertOk()->assertSee('ورود به فضای کاری');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
    }

    public function test_active_staff_can_sign_in_and_sign_out(): void
    {
        $this->travelTo('2026-11-19 09:00:00');
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()
            ->assertSee($user->name)
            ->assertSee('۱۴۰۵/۰۸/۲۸، پنجشنبه')
            ->assertSee('aria-label="خروج از حساب"', false);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'ایمیل یا رمز عبور صحیح نیست یا حساب غیرفعال است.']);

        $this->assertGuest();
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_missing_credentials_receive_persian_validation_errors(): void
    {
        $this->post('/login', [])->assertSessionHasErrors([
            'email' => 'ایمیل را وارد کنید.', 'password' => 'رمز عبور را وارد کنید.',
        ]);
    }

    public function test_deactivated_staff_lose_access_to_existing_session(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_staff_name_is_escaped_in_workspace(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
