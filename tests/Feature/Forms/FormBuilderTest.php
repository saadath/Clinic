<?php

namespace Tests\Feature\Forms;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Forms\ManageForm;
use Database\Seeders\ExampleFormSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function doctor(): Doctor
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'doctor')->sole());

        return Doctor::factory()->create(['user_id' => $user->id]);
    }

    private function template(Doctor $doctor, ?array $fields = null): FormTemplate
    {
        return app(ManageForm::class)->create($doctor->user, $doctor->id, 'Test form', ['sections' => [['key' => 'main', 'title' => 'Main', 'columns' => 2, 'fields' => $fields ?? [['key' => 'symptoms', 'type' => 'long_text', 'label' => 'Symptoms', 'required' => true]]]]]);
    }

    public function test_doctor_can_design_publish_and_save_sample_without_changing_published_version(): void
    {
        $doctor = $this->doctor();
        $this->actingAs($doctor->user)->get('/forms/create')->assertOk();
        $this->post('/forms', ['name' => 'New form', 'doctor_id' => $doctor->id])->assertRedirect();
        $template = FormTemplate::sole();
        $version = $template->versions()->sole();
        $schema = ['sections' => [['key' => 'main', 'title' => 'Main', 'columns' => 2, 'fields' => [['key' => 'symptoms', 'type' => 'long_text', 'label' => 'Symptoms', 'required' => true]]]]];

        $this->get('/form-versions/'.$version->id.'/edit')->assertOk();
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/form-versions/'.$version->id.'/publish', ['revision' => 2])->assertRedirect();
        $this->get('/form-versions/'.$version->id)->assertOk()->assertSee('Symptoms');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['symptoms' => '<script>sample</script>']])->assertRedirect();
        $submission = FormSubmission::sole();
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertDontSee('<script>sample</script>', false);
        $originalHash = $version->fresh()->schema_hash;
        $this->post('/forms/'.$template->public_id.'/draft')->assertRedirect();
        $next = $template->versions()->orderByDesc('version_number')->first();
        $schema['sections'][0]['fields'][0]['label'] = 'Changed label';
        $this->put('/form-versions/'.$next->id, ['schema' => $schema, 'revision' => 1])->assertRedirect();
        $this->post('/form-versions/'.$next->id.'/publish', ['revision' => 2])->assertRedirect();

        $this->assertSame($originalHash, $version->fresh()->schema_hash);
        $this->assertSame($version->id, $submission->form_version_id);
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('Symptoms')->assertDontSee('Changed label');
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 2])->assertConflict();
    }

    public function test_stale_draft_does_not_overwrite_saved_changes(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $schema = $version->schema_json;

        $this->actingAs($doctor->user)->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 1])->assertRedirect();
        $schema['sections'][0]['title'] = 'Stale';
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 1])->assertConflict();

        $this->assertSame('Main', $version->fresh()->schema_json['sections'][0]['title']);
    }

    public static function invalidFields(): array
    {
        return [
            'unknown type' => [[['key' => 'x', 'type' => 'execute', 'label' => 'X']]],
            'duplicate key' => [[['key' => 'x', 'type' => 'short_text', 'label' => 'X'], ['key' => 'x', 'type' => 'long_text', 'label' => 'Y']]],
            'unknown reference' => [[['key' => 'x', 'type' => 'short_text', 'label' => 'X', 'condition' => ['op' => 'exists', 'field' => 'missing']]]],
            'cycle' => [[['key' => 'x', 'type' => 'short_text', 'label' => 'X', 'condition' => ['op' => 'exists', 'field' => 'x']]]],
            'unsafe expression' => [[['key' => 'x', 'type' => 'calculated', 'label' => 'X', 'calculation' => ['op' => 'eval', 'left' => ['number' => 1], 'right' => ['number' => 1]]]]],
            'unexpected key' => [[['key' => 'x', 'type' => 'short_text', 'label' => 'X', 'api_key' => 'forged']]],
            'nested repeater' => [[['key' => 'x', 'type' => 'repeater', 'label' => 'X', 'children' => [['key' => 'y', 'type' => 'repeater', 'label' => 'Y']]]]],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_schema_is_rejected_without_modifying_draft(array $fields): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $schema = $version->schema_json;
        $schema['sections'][0]['fields'] = $fields;

        $this->actingAs($doctor->user)->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 1])->assertSessionHasErrors();
        $this->assertSame(1, $version->fresh()->revision);
    }

    public function test_doctors_are_isolated_and_assistant_cannot_design_or_submit_forbidden_field(): void
    {
        $doctor = $this->doctor();
        $other = $this->doctor();
        $template = $this->template($doctor, [
            ['key' => 'public_note', 'type' => 'short_text', 'label' => 'Public note', 'assistant_editable' => true],
            ['key' => 'secret_note', 'type' => 'long_text', 'label' => 'Secret unique label', 'confidentiality' => 'confidential'],
        ]);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'doctor-assistant')->sole());
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'assignment_type' => 'assistant']);

        $this->actingAs($other->user)->get('/form-versions/'.$version->id)->assertNotFound();
        $directoryRole = Role::create(['name' => 'directory-viewer', 'label' => 'Directory viewer']);
        $directoryRole->permissions()->attach(Permission::where('name', 'doctors.view-all')->sole());
        $other->user->roles()->attach($directoryRole);
        $this->actingAs($other->user->fresh())->get('/form-versions/'.$version->id)->assertNotFound();
        $this->actingAs($staff)->get('/form-versions/'.$version->id)->assertOk()->assertDontSee('Secret unique label')->assertDontSee('secret_note');
        $this->get('/form-versions/'.$version->id.'/edit')->assertForbidden();
        $this->post('/form-versions/'.$version->id.'/publish', ['revision' => 1])->assertForbidden();
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['secret_note' => 'forged']])->assertSessionHasErrors('values.secret_note');
        $this->assertDatabaseCount('form_submissions', 0);
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['public_note' => 'sample']])->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_examples_are_independent_calculate_bmi_and_keep_repeater_ids(): void
    {
        $doctor = $this->doctor();
        $this->seed(ExampleFormSeeder::class);
        $internal = FormTemplate::where('name', 'Internal Medicine — Example')->sole()->versions()->sole();
        $eye = FormTemplate::where('name', 'Ophthalmology — Example')->sole()->versions()->sole();
        $this->actingAs($doctor->user)->get('/form-versions/'.$eye->id)->assertOk()->assertSee('IOP OD')->assertDontSee('Weight');
        $this->post('/form-versions/'.$internal->id.'/submissions', ['values' => ['weight' => 80, 'height_m' => 2, 'allergy' => 'No', 'allergy_details' => 'Hidden input', 'medications' => ['row_second' => ['drug_name' => 'B', 'dose' => '2'], 'row_first' => ['drug_name' => 'A', 'dose' => '1']]]])->assertSessionHasNoErrors()->assertRedirect();
        $submission = FormSubmission::sole();

        $this->assertSame(20, $submission->values_json['bmi']);
        $this->assertArrayNotHasKey('allergy_details', $submission->values_json);
        $this->assertSame(['row_second', 'row_first'], array_keys($submission->values_json['medications']));
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('row_second', false);
        $this->post('/form-versions/'.$internal->id.'/submissions', ['values' => ['weight' => 80, 'height_m' => 0]])->assertSessionHasErrors();
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_copy_requires_share_and_never_changes_source(): void
    {
        $doctor = $this->doctor();
        $recipient = $this->doctor();
        $template = $this->template($doctor);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);

        $this->actingAs($recipient->user)->post('/forms/'.$template->public_id.'/copy', ['doctor_id' => $recipient->id, 'name' => 'Copied'])->assertNotFound();
        $this->actingAs($doctor->user)->post('/forms/'.$template->public_id.'/share', ['doctor_id' => $recipient->id])->assertRedirect();
        $this->actingAs($recipient->user)->get('/forms')->assertOk()->assertSee('Test form');
        $this->post('/forms/'.$template->public_id.'/copy', ['doctor_id' => $recipient->id, 'name' => 'Copied'])->assertRedirect();

        $this->assertDatabaseHas('form_templates', ['name' => 'Copied', 'owner_doctor_id' => $recipient->id]);
        $this->assertSame('published', $version->fresh()->status);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_guests_and_unprivileged_users_are_rejected(): void
    {
        $this->get('/forms')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/forms')->assertForbidden();
        $this->post('/forms', [])->assertForbidden();

        $this->assertDatabaseCount('form_templates', 0);
    }

    public function test_revoked_share_stops_copy_and_archiving_preserves_samples_but_removes_default(): void
    {
        $doctor = $this->doctor();
        $recipient = $this->doctor();
        $template = $this->template($doctor);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);

        $this->actingAs($doctor->user)->post('/forms/'.$template->public_id.'/share', ['doctor_id' => $recipient->id])->assertRedirect();
        $this->post('/forms/'.$template->public_id.'/share', ['doctor_id' => $recipient->id, 'revoke' => true])->assertRedirect();
        $this->actingAs($recipient->user)->post('/forms/'.$template->public_id.'/copy', ['doctor_id' => $recipient->id, 'name' => 'Denied'])->assertNotFound();
        $this->actingAs($doctor->user)->post('/forms/'.$template->public_id.'/default', ['purpose' => 'encounter'])->assertRedirect();
        $this->assertDatabaseHas('doctor_form_defaults', ['doctor_id' => $doctor->id, 'form_template_id' => $template->id]);
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['symptoms' => 'Historical']])->assertSessionHasNoErrors()->assertRedirect();
        $submission = FormSubmission::sole();
        $this->put('/forms/'.$template->public_id, ['name' => 'Archived', 'active' => false])->assertRedirect();

        $this->assertDatabaseMissing('doctor_form_defaults', ['form_template_id' => $template->id]);
        $this->assertDatabaseHas('form_templates', ['id' => $template->id, 'active' => false]);
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('Historical');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['symptoms' => 'New']])->assertForbidden();
        $this->post('/forms/'.$template->public_id.'/default', ['purpose' => 'encounter'])->assertUnprocessable();
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_hidden_values_cannot_trigger_downstream_conditions_or_calculations(): void
    {
        $doctor = $this->doctor();
        $template = $this->template($doctor, [
            ['key' => 'gate', 'type' => 'select', 'label' => 'Gate', 'options' => ['Yes', 'No']],
            ['key' => 'hidden_number', 'type' => 'decimal', 'label' => 'Conditional number', 'condition' => ['op' => 'equals', 'field' => 'gate', 'value' => 'Yes']],
            ['key' => 'dependent', 'type' => 'short_text', 'label' => 'Dependent', 'required' => true, 'condition' => ['op' => 'gt', 'field' => 'hidden_number', 'value' => 1]],
        ]);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);

        $this->actingAs($doctor->user)->post('/form-versions/'.$version->id.'/submissions', ['values' => ['gate' => 'No', 'hidden_number' => 10]])->assertSessionHasNoErrors()->assertRedirect();

        $values = FormSubmission::sole()->values_json;
        $this->assertSame(['gate' => 'No'], $values);
    }

    public function test_assistant_required_confidential_fields_and_calculations_are_not_exposed_or_required(): void
    {
        $doctor = $this->doctor();
        $template = $this->template($doctor, [
            ['key' => 'public_note', 'type' => 'short_text', 'label' => 'Public note', 'assistant_editable' => true],
            ['key' => 'secret', 'type' => 'decimal', 'label' => 'Private value', 'required' => true, 'confidentiality' => 'confidential'],
            ['key' => 'secret_total', 'type' => 'calculated', 'label' => 'Private total', 'confidentiality' => 'confidential', 'calculation' => ['field' => 'secret']],
            ['key' => 'rows', 'type' => 'repeater', 'label' => 'Rows', 'assistant_editable' => true, 'children' => [
                ['key' => 'allowed', 'type' => 'short_text', 'label' => 'Allowed', 'assistant_editable' => true],
                ['key' => 'private_child', 'type' => 'short_text', 'label' => 'Private child', 'confidentiality' => 'confidential'],
            ]],
        ]);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('name', 'doctor-assistant')->sole());
        $assignment = DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $staff->id, 'assignment_type' => 'assistant']);

        $this->actingAs($staff)->get('/form-versions/'.$version->id)->assertOk()->assertDontSee('secret_total')->assertDontSee('private_child');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['public_note' => 'Allowed', 'rows' => ['row_12345' => ['private_child' => 'Forged']]]])->assertSessionHasErrors('values.rows.row_12345.private_child');
        $this->assertDatabaseCount('form_submissions', 0);
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['public_note' => 'Allowed']])->assertSessionHasNoErrors()->assertRedirect();
        $submission = FormSubmission::sole();
        $this->assertArrayNotHasKey('secret_total', $submission->values_json);
        $assignment->update(['ends_at' => now()]);
        $this->get('/form-submissions/'.$submission->public_id)->assertNotFound();
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => []])->assertNotFound();
    }

    public function test_choice_repeater_and_typed_values_round_trip_and_invalid_values_are_rejected(): void
    {
        $doctor = $this->doctor();
        $template = $this->template($doctor, [
            ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ['key' => 'integer', 'type' => 'integer', 'label' => 'Integer', 'min' => 1, 'max' => 10],
            ['key' => 'date', 'type' => 'date', 'label' => 'Date'],
            ['key' => 'rich', 'type' => 'rich_text', 'label' => 'Rich'],
            ['key' => 'choice', 'type' => 'radio', 'label' => 'Choice', 'options' => ['A', 'B']],
            ['key' => 'rows', 'type' => 'repeater', 'label' => 'Rows', 'children' => [['key' => 'choices', 'type' => 'checkbox', 'label' => 'Choices', 'options' => ['A', 'B']]]],
        ]);
        $version = $template->versions()->sole();
        app(ManageForm::class)->publish($doctor->user, $version, 1);
        $values = ['email' => 'doctor@example.com', 'integer' => '3', 'date' => '2026-11-17', 'rich' => '<b>Safe</b>', 'choice' => 'A', 'rows' => ['row_12345' => ['choices' => ['A', 'B']]]];

        $this->actingAs($doctor->user)->get('/form-versions/'.$version->id)->assertOk()->assertSee('type="radio"', false)->assertSee('type="checkbox"', false);
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => $values])->assertSessionHasNoErrors()->assertRedirect();
        $submission = FormSubmission::sole();
        $this->assertSame(3, $submission->values_json['integer']);
        $this->assertSame('Safe', $submission->values_json['rich']);
        $this->assertSame(['A', 'B'], $submission->values_json['rows']['row_12345']['choices']);
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('doctor@example.com');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => [...$values, 'email' => 'invalid']])->assertSessionHasErrors('values.email');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => [...$values, 'integer' => '2.5']])->assertSessionHasErrors('values.integer');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => [...$values, 'date' => '2026-02-30']])->assertSessionHasErrors('values.date');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => [...$values, 'choice' => 'forged']])->assertSessionHasErrors('values.choice');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => [...$values, 'rows' => ['row_12345' => ['choices' => ['forged']]]]])->assertSessionHasErrors('values.rows.row_12345.choices');
        $this->assertDatabaseCount('form_submissions', 1);
    }
}
