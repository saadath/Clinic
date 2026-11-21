<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function account(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    public function test_doctor_dashboard_shows_profile_and_authorized_widgets(): void
    {
        $doctorUser = $this->account('doctor');
        $doctorUser->update(['name' => 'دکتر آزمون']);
        Doctor::factory()->create(['user_id' => $doctorUser->id, 'specialty' => 'داخلی']);

        $this->actingAs($doctorUser)->get('/dashboard')->assertOk()
            ->assertSee('داشبورد پزشک')
            ->assertSee('دکتر آزمون')
            ->assertSee('داخلی')
            ->assertSee(__('dashboard.doctor_profile'));
    }

    public static function dashboardTitles(): array
    {
        return [
            'super admin' => ['super-admin', 'داشبورد مدیر ارشد'],
            'clinic administrator' => ['clinic-administrator', 'داشبورد مدیر کلینیک'],
            'doctor' => ['doctor', 'داشبورد پزشک'],
            'doctor assistant' => ['doctor-assistant', 'داشبورد دستیار پزشک'],
            'general receptionist' => ['general-receptionist', 'داشبورد منشی عمومی'],
            'receptionist' => ['receptionist', 'داشبورد منشی'],
            'accountant' => ['accountant', 'داشبورد حسابدار'],
        ];
    }

    #[DataProvider('dashboardTitles')]
    public function test_dashboard_heading_matches_the_users_role(string $role, string $title): void
    {
        $this->actingAs($this->account($role))->get('/dashboard')->assertOk()->assertSee($title);
    }

    public function test_dashboard_hides_clinic_widgets_from_accounts_without_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $accountant = User::factory()->create();
        $accountant->roles()->attach(Role::where('name', 'accountant')->sole());

        $this->actingAs($accountant)->get('/dashboard')->assertOk()
            ->assertSee('داشبورد حسابدار')
            ->assertSee(__('dashboard.quick_actions'))
            ->assertDontSee(route('patients.index'), false)
            ->assertDontSee(route('forms.index'), false);
    }
}
