<?php

namespace Tests\Feature\Patients;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PatientControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function account(string $role = 'super-admin'): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [...['first_name' => 'سارا', 'last_name' => 'احمدی', 'national_id' => '0013547895', 'mobile' => '09123456789', 'birth_date' => '1990-03-21', 'sex' => 'female'], ...$overrides];
    }

    public function test_guests_and_accounts_without_permissions_cannot_access_patient_operations(): void
    {
        $patient = Patient::factory()->create();
        $this->get('/patients')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/patients')->assertForbidden();
        $this->get('/patients/create')->assertForbidden();
        $this->get('/patients/'.$patient->public_id)->assertForbidden();
        $this->post('/patients', [])->assertForbidden();
        $this->put('/patients/'.$patient->public_id, [])->assertForbidden();
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_registration_normalizes_digits_preserves_zero_and_ignores_protected_fields(): void
    {
        $admin = $this->account();
        $this->actingAs($admin)->post('/patients', $this->payload(['national_id' => '۰۰۱۳۵۴۷۸۹۵', 'mobile' => '٠٩١٢٣٤٥٦٧٨٩', 'created_by' => 999, 'public_id' => 'forged']))->assertSessionHasNoErrors()->assertRedirect();

        $patient = Patient::sole();
        $this->assertDatabaseHas('patients', ['national_id' => '0013547895', 'mobile' => '09123456789', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->assertNotSame('forged', $patient->public_id);
        $this->get('/patients/'.$patient->public_id)->assertOk()->assertSee('سارا');
        $this->get('/patients/'.$patient->id)->assertNotFound();
        $this->get('/patients/create')->assertOk();
        $this->get('/patients/'.$patient->public_id.'/edit')->assertOk();
    }

    public static function invalidInputs(): array
    {
        return [
            'checksum' => ['national_id', '0013547899'],
            'repeated' => ['national_id', '1111111111'],
            'short ID' => ['national_id', '123'],
            'mobile' => ['mobile', '091234'],
            'future date' => ['birth_date', '2999-01-01'],
            'impossible date' => ['birth_date', '1990-02-30'],
            'old date' => ['birth_date', '1899-12-31'],
            'sex' => ['sex', 'invalid'],
            'email' => ['email', 'invalid'],
            'emergency mobile' => ['emergency_mobile', '123'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_registration_does_not_write_patient(string $field, string $value): void
    {
        $this->actingAs($this->account())->post('/patients', $this->payload([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_duplicate_national_id_is_blocked_and_visible_record_is_suggested(): void
    {
        $admin = $this->account();
        $patient = Patient::factory()->create(['national_id' => '0013547895']);

        $this->actingAs($admin)->from('/patients/create')->post('/patients', $this->payload())->assertSessionHasErrors(['national_id' => __('patients.duplicate')]);
        $this->get('/patients/create')->assertOk()->assertSee(route('patients.show', $patient), false);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_shared_family_mobile_requires_confirmation(): void
    {
        $admin = $this->account();
        Patient::factory()->create(['mobile' => '09123456789']);

        $this->actingAs($admin)->post('/patients', $this->payload())->assertSessionHasErrors(['shared_mobile_confirmed' => __('patients.shared_warning')]);
        $this->assertDatabaseCount('patients', 1);
        $this->post('/patients', $this->payload(['shared_mobile_confirmed' => '1']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('patients', 2);
    }

    public function test_edit_keeps_creator_and_existing_links_and_escapes_free_text(): void
    {
        $admin = $this->account();
        $patient = Patient::factory()->create(['national_id' => '0013547895']);
        $doctor = Doctor::factory()->create();
        $patient->doctors()->attach($doctor, ['granted_by' => $admin->id]);
        $creator = $patient->created_by;
        $fields = ['first_name', 'last_name', 'address', 'notes', 'occupation', 'emergency_name', 'insurance_name', 'insurance_number', 'referral_source'];
        $data = $this->payload(array_fill_keys($fields, '<script>unsafe</script>'));

        $this->actingAs($admin)->put('/patients/'.$patient->public_id, $data)->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $patient));

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'created_by' => $creator, 'updated_by' => $admin->id, 'notes' => '<script>unsafe</script>']);
        $this->assertDatabaseHas('patient_doctor', ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'granted_by' => $admin->id]);
        $this->get('/patients/'.$patient->public_id)->assertOk()->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
    }

    public function test_doctor_search_and_direct_access_are_scoped_before_validation(): void
    {
        $user = $this->account('doctor');
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        $visible = Patient::factory()->create(['last_name' => 'VisibleUnique']);
        $hidden = Patient::factory()->create(['last_name' => 'HiddenUnique']);
        $visible->doctors()->attach($doctor, ['granted_by' => $user->id]);

        $this->actingAs($user)->get('/patients')->assertOk()->assertSee('VisibleUnique')->assertDontSee('HiddenUnique');
        $this->get('/patients?q='.$hidden->national_id)->assertOk()->assertDontSee('HiddenUnique');
        $this->get('/patients/'.$hidden->public_id)->assertNotFound();
        $this->get('/patients/'.$hidden->public_id.'/edit')->assertNotFound();
        $this->put('/patients/'.$hidden->public_id, [])->assertNotFound();
        $this->assertDatabaseHas('patients', ['id' => $hidden->id, 'last_name' => 'HiddenUnique']);
    }

    public function test_staff_loses_patient_access_when_assignment_ends(): void
    {
        $this->freezeTime();
        $staff = $this->account('receptionist');
        $doctorUser = $this->account('doctor');
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);
        $patient = Patient::factory()->create();
        $patient->doctors()->attach($doctor, ['granted_by' => $staff->id]);
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'ends_at' => now()->addHour()]);

        $this->actingAs($staff)->get('/patients/'.$patient->public_id)->assertOk();
        $this->travel(1)->hours();
        $this->get('/patients/'.$patient->public_id)->assertNotFound();
        $this->put('/patients/'.$patient->public_id, [])->assertNotFound();
    }

    public function test_scoped_registration_requires_authorized_doctor_and_cannot_share_existing_patient(): void
    {
        $staff = $this->account('receptionist');
        $doctors = [];
        for ($index = 0; $index < 2; $index++) {
            $user = $this->account('doctor');
            $doctors[] = $doctor = Doctor::factory()->create(['user_id' => $user->id]);
            DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id]);
        }
        $unassigned = Doctor::factory()->create();

        $this->actingAs($staff)->post('/patients', $this->payload())->assertSessionHasErrors('doctor_id');
        $this->post('/patients', $this->payload(['doctor_id' => $unassigned->id]))->assertSessionHasErrors('doctor_id');
        $this->post('/patients', $this->payload(['doctor_id' => $doctors[0]->id]))->assertSessionHasNoErrors()->assertRedirect();
        $patient = Patient::sole();
        $this->put('/patients/'.$patient->public_id, $this->payload(['doctor_id' => $doctors[1]->id]))->assertSessionHasErrors('doctor_id');
        $this->assertDatabaseMissing('patient_doctor', ['patient_id' => $patient->id, 'doctor_id' => $doctors[1]->id]);
    }

    public function test_general_reception_can_find_existing_patient_and_add_doctor_link(): void
    {
        $staff = $this->account('general-receptionist');
        $patient = Patient::factory()->create(['national_id' => '0013547895', 'mobile' => '09123456789']);
        $doctor = Doctor::factory()->create();

        $this->actingAs($staff)->get('/patients?q=۰۰۱۳۵۴۷۸۹۵')->assertOk()->assertSee($patient->last_name);
        $this->put('/patients/'.$patient->public_id, $this->payload(['doctor_id' => $doctor->id]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('patient_doctor', ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'granted_by' => $staff->id]);
    }

    public function test_duplicate_suggestions_do_not_disclose_hidden_patient(): void
    {
        $user = $this->account('doctor');
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        $hidden = Patient::factory()->create(['national_id' => '0013547895', 'last_name' => 'HiddenDuplicateName']);

        $this->actingAs($user)->from('/patients/create')->post('/patients', $this->payload(['doctor_id' => $doctor->id]))->assertSessionHasErrors('national_id');
        $this->get('/patients/create')->assertOk()->assertDontSee('HiddenDuplicateName')->assertDontSee($hidden->public_id);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_database_rejects_duplicate_national_id_even_outside_request_validation(): void
    {
        Patient::factory()->create(['national_id' => '0013547895']);

        $this->expectException(UniqueConstraintViolationException::class);
        Patient::factory()->create(['national_id' => '0013547895']);
    }
}
