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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_doctor_can_save_form_and_later_changes_preserve_saved_samples(): void
    {
        $doctor = $this->doctor();
        $this->actingAs($doctor->user)->get('/forms/create')->assertOk();
        $this->post('/forms', ['name' => 'New form', 'doctor_id' => $doctor->id])->assertRedirect();
        $template = FormTemplate::sole();
        $version = $template->versions()->sole();
        $schema = ['sections' => [['key' => 'main', 'title' => 'Main', 'columns' => 2, 'fields' => [['key' => 'symptoms', 'type' => 'long_text', 'label' => 'Symptoms', 'required' => true]]]]];

        $this->get('/form-versions/'.$version->id.'/edit')
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee(__('forms.save'))
            ->assertDontSee(__('forms.publish'));
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('published', $version->fresh()->status);
        $this->get('/form-versions/'.$version->id)->assertOk()->assertSee('Symptoms');
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => ['symptoms' => '<script>sample</script>']])->assertRedirect();
        $submission = FormSubmission::sole();
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertDontSee('<script>sample</script>', false);
        $originalHash = $version->fresh()->schema_hash;
        $this->get('/forms/'.$template->public_id.'/edit')->assertRedirect('/form-versions/'.$version->id.'/edit');
        $schema['sections'][0]['fields'][0]['label'] = 'Changed label';
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 2])->assertSessionHasNoErrors()->assertRedirect();
        $next = $template->versions()->orderByDesc('version_number')->firstOrFail();

        $this->assertSame($originalHash, $version->fresh()->schema_hash);
        $this->assertSame(2, $next->version_number);
        $this->assertSame('Changed label', $next->schema_json['sections'][0]['fields'][0]['label']);
        $this->assertSame($version->id, $submission->form_version_id);
        $this->get('/form-versions/'.$version->id.'/edit')->assertRedirect('/form-versions/'.$next->id.'/edit');
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('Symptoms')->assertDontSee('Changed label');
        $this->put('/form-versions/'.$version->id, ['schema' => $schema, 'revision' => 2])->assertConflict();
        $this->post('/form-versions/'.$version->id.'/publish', ['revision' => 2])->assertNotFound();
    }

    public function test_editor_can_save_schema_from_one_form_submission(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $schema = ['sections' => [['key' => 'main', 'title' => 'Main', 'columns' => 2, 'fields' => [[
            'key' => 'height', 'type' => 'decimal', 'label' => 'Height', 'description' => '', 'width' => 1, 'required' => false, 'active' => true,
            'default' => '', 'min' => null, 'max' => null, 'options' => [], 'unit' => 'cm', 'condition' => null, 'calculation' => null, 'children' => [],
            'confidentiality' => 'normal', 'assistant_editable' => false, 'receptionist_visible' => false, 'exportable' => true,
            'ai' => ['enabled' => false, 'methods' => [], 'task' => '', 'review_required' => true],
        ]]]]];

        $this->actingAs($doctor->user)->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => json_encode($schema, JSON_THROW_ON_ERROR)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame('height', $version->fresh()->schema_json['sections'][0]['fields'][0]['key']);
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
        $this->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => $version->schema_json])->assertForbidden();
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

    public function test_owner_can_delete_form_and_its_shares_and_defaults(): void
    {
        $doctor = $this->doctor();
        $recipient = $this->doctor();
        $template = $this->template($doctor);
        $remaining = $this->template($doctor);

        $this->actingAs($doctor->user)->get('/forms')->assertOk()->assertSee(__('forms.delete'));
        $this->post('/forms/'.$template->public_id.'/share', ['doctor_id' => $recipient->id])->assertRedirect();
        $this->post('/forms/'.$template->public_id.'/default', ['purpose' => 'encounter'])->assertRedirect();

        $this->delete('/forms/'.$template->public_id)
            ->assertRedirect('/forms')
            ->assertSessionHas('status', __('forms.deleted'));

        $this->assertDatabaseMissing('form_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('form_versions', ['form_template_id' => $template->id]);
        $this->assertDatabaseMissing('form_template_shares', ['form_template_id' => $template->id]);
        $this->assertDatabaseMissing('doctor_form_defaults', ['form_template_id' => $template->id]);
        $this->assertDatabaseHas('form_templates', ['id' => $remaining->id]);
    }

    public function test_form_with_saved_submissions_cannot_be_deleted(): void
    {
        $doctor = $this->doctor();
        $template = $this->template($doctor);
        $version = $template->versions()->sole();
        $this->actingAs($doctor->user)->post('/form-versions/'.$version->id.'/submissions', ['values' => ['symptoms' => 'Kept']])->assertRedirect();

        $this->delete('/forms/'.$template->public_id)->assertRedirect()->assertSessionHasErrors('form');

        $this->assertDatabaseHas('form_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('form_versions', ['id' => $version->id]);
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_only_owner_or_administrator_can_delete_form(): void
    {
        $owner = $this->doctor();
        $otherDoctor = $this->doctor();
        $template = $this->template($owner);

        $this->actingAs($otherDoctor->user)->delete('/forms/'.$template->public_id)->assertNotFound();
        $this->actingAs(User::factory()->create())->delete('/forms/'.$template->public_id)->assertForbidden();
        $this->assertDatabaseHas('form_templates', ['id' => $template->id]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super-admin')->sole());
        $this->actingAs($admin)->delete('/forms/'.$template->public_id)->assertRedirect('/forms');
        $this->assertDatabaseMissing('form_templates', ['id' => $template->id]);
    }

    public function test_revoked_share_stops_copy_and_archiving_preserves_samples_but_removes_default(): void
    {
        $doctor = $this->doctor();
        $recipient = $this->doctor();
        $template = $this->template($doctor);
        $version = $template->versions()->sole();

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

    public function test_choice_options_can_be_saved_and_used_for_all_three_controls(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $schema = $version->schema_json;
        $schema['sections'][0]['fields'] = array_map(fn (string $type): array => [
            'key' => $type, 'type' => $type, 'label' => $type, 'options' => ['گزینه اول', 'گزینه دوم'],
        ], ['select', 'radio', 'multi_select']);

        $this->actingAs($doctor->user)->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => json_encode($schema, JSON_THROW_ON_ERROR)])
            ->assertSessionHasNoErrors()->assertRedirect();

        $values = ['select' => 'گزینه اول', 'radio' => 'گزینه دوم', 'multi_select' => ['گزینه اول', 'گزینه دوم']];
        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => $values])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals($values, FormSubmission::sole()->values_json);
    }

    public function test_legacy_field_types_cannot_be_added_but_existing_fields_can_be_saved(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor, [['key' => 'legacy_date', 'type' => 'date', 'label' => 'Legacy date']])->versions()->sole();
        $schema = $version->schema_json;
        $schema['sections'][0]['fields'][] = ['key' => 'new_color', 'type' => 'color', 'label' => 'Color'];

        $this->actingAs($doctor->user)->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => $schema])->assertSessionHasErrors('schema');
        $this->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => $version->schema_json])->assertSessionHasNoErrors();
    }

    public function test_time_is_not_offered_or_accepted_as_a_new_medical_form_field(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $schema = $version->schema_json;
        $schema['sections'][0]['fields'][] = ['key' => 'visit_time', 'type' => 'time', 'label' => 'Visit time'];

        $this->actingAs($doctor->user)->get('/form-versions/'.$version->id.'/edit')->assertOk()->assertDontSee('<option value="time">', false);
        $this->put('/form-versions/'.$version->id, ['revision' => 1, 'schema' => json_encode($schema, JSON_THROW_ON_ERROR)])->assertSessionHasErrors('schema');
    }

    public function test_numeric_limits_and_twenty_four_hour_time_are_enforced_on_submission(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor, [
            ['key' => 'weight', 'type' => 'medical_unit', 'label' => 'Weight', 'unit' => 'kg', 'min' => 1.5, 'max' => 20.5],
            ['key' => 'levels', 'type' => 'integer', 'label' => 'Levels', 'min' => 2, 'max' => 7],
            ['key' => 'pain', 'type' => 'slider', 'label' => 'Pain'],
            ['key' => 'time', 'type' => 'time', 'label' => 'Time'],
        ])->versions()->sole();
        $url = '/form-versions/'.$version->id.'/submissions';

        $this->actingAs($doctor->user)->post($url, ['values' => ['weight' => '20.51', 'levels' => '8', 'pain' => '101', 'time' => '24:00']])->assertSessionHasErrors('values.weight');
        $this->post($url, ['values' => ['weight' => '1.49']])->assertSessionHasErrors('values.weight');
        $this->post($url, ['values' => ['levels' => '8']])->assertSessionHasErrors('values.levels');
        $this->post($url, ['values' => ['pain' => '101']])->assertSessionHasErrors('values.pain');
        $this->post($url, ['values' => ['time' => '24:00']])->assertSessionHasErrors('values.time');
        $this->post($url, ['values' => ['weight' => '20.5', 'levels' => '2', 'pain' => '0', 'time' => '23:59']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('23:59', FormSubmission::sole()->values_json['time']);
    }

    public function test_slider_uses_whole_number_steps_and_rejects_fractional_submissions(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor, [['key' => 'pain', 'type' => 'slider', 'label' => 'Pain', 'min' => -2, 'max' => 5]])->versions()->sole();
        $url = '/form-versions/'.$version->id;

        $this->actingAs($doctor->user)->get($url)->assertOk()->assertSee('type="range" min="-2" max="5" step="1"', false);
        $this->post($url.'/submissions', ['values' => ['pain' => '2.5']])->assertSessionHasErrors('values.pain');
        $this->post($url.'/submissions', ['values' => ['pain' => '0']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, FormSubmission::sole()->values_json['pain']);
    }

    public function test_radio_inputs_each_have_a_clickable_option_label(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor, [['key' => 'choice', 'type' => 'radio', 'label' => 'Radio group', 'options' => ['First choice', 'Second choice']]])->versions()->sole();

        $response = $this->actingAs($doctor->user)->get('/form-versions/'.$version->id)->assertOk();

        foreach (['First choice', 'Second choice'] as $option) {
            $option = preg_quote($option, '/');
            $this->assertMatchesRegularExpression('/<label\b[^>]*>\s*<input type="radio"[^>]*value="'.$option.'"[^>]*>\s*<span>'.$option.'<\/span>\s*<\/label>/', $response->getContent());
        }
    }

    public function test_radio_and_checkbox_groups_render_with_their_saved_option_layout(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor, [
            ['key' => 'choice', 'type' => 'radio', 'label' => 'Radio group', 'options' => ['First', 'Second']],
            ['key' => 'flags', 'type' => 'checkbox', 'label' => 'Checkbox group', 'options' => ['First', 'Second']],
        ])->versions()->sole();
        $url = '/form-versions/'.$version->id;

        $this->actingAs($doctor->user)->get($url.'/edit')->assertOk()->assertSee(__('forms.options_vertical'));
        $this->get($url)->assertOk()->assertDontSee('class="flex flex-col items-start gap-2"', false);

        $schema = $version->schema_json;
        foreach ($schema['sections'][0]['fields'] as &$field) {
            $field['options_layout'] = 'vertical';
        }
        unset($field);
        $this->put($url, ['schema' => $schema, 'revision' => $version->revision])->assertSessionHasNoErrors()->assertRedirect();

        $preview = $this->get($url)->assertOk();
        foreach (['radio', 'checkbox'] as $type) {
            $this->assertMatchesRegularExpression('/class="flex flex-col items-start gap-2">\s*<label[^>]*>\s*<input type="'.$type.'"/', $preview->getContent());
        }
    }

    public function test_single_checkbox_can_be_added_and_saves_both_boolean_states(): void
    {
        $doctor = $this->doctor();
        $version = $this->template($doctor)->versions()->sole();
        $url = '/form-versions/'.$version->id;
        $schema = $version->schema_json;
        $schema['sections'][0]['fields'] = [
            ['key' => 'follow_up', 'type' => 'single_checkbox', 'label' => 'Follow up', 'default' => true],
            ['key' => 'consent', 'type' => 'single_checkbox', 'label' => 'Consent', 'required' => true],
        ];

        $this->actingAs($doctor->user)->get($url.'/edit')->assertOk()->assertSee('<option value="single_checkbox">', false);
        $this->put($url, ['revision' => 1, 'schema' => $schema])->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('type="checkbox"', false)->assertSee('Follow up');
        $this->post($url.'/submissions', ['values' => ['consent' => '0']])->assertSessionHasErrors('values.consent');
        $this->post($url.'/submissions', ['values' => ['follow_up' => 'invalid', 'consent' => '1']])->assertSessionHasErrors('values.follow_up');
        $this->post($url.'/submissions', ['values' => ['follow_up' => '0', 'consent' => '1']])->assertSessionHasNoErrors();

        $values = FormSubmission::sole()->values_json;
        $this->assertFalse($values['follow_up']);
        $this->assertTrue($values['consent']);
    }

    public function test_pdf_field_accepts_multiple_documents_and_single_image_stays_single(): void
    {
        Storage::fake('local');
        $doctor = $this->doctor();
        $version = $this->template($doctor, [
            ['key' => 'documents', 'type' => 'pdf', 'label' => 'Documents', 'required' => true],
            ['key' => 'photo', 'type' => 'image', 'label' => 'Photo'],
        ])->versions()->sole();
        $url = '/form-versions/'.$version->id;
        $pdf = static fn (string $name) => UploadedFile::fake()->create($name, 20, 'application/pdf');
        $image = static fn () => UploadedFile::fake()->create('photo.png', 20, 'image/png');

        $preview = $this->actingAs($doctor->user)->get($url)->assertOk()
            ->assertSee('data-preview-kind="image"', false);
        $this->assertMatchesRegularExpression('/<input\b[^>]*accept="application\/pdf,\.pdf"\s+multiple\b/', $preview->getContent());
        $this->post($url.'/submissions', ['values' => ['documents' => [$image()]]])->assertSessionHasErrors('values.documents');
        $this->post($url.'/submissions', ['values' => ['documents' => [$pdf('report.pdf')], 'photo' => [$image()]]])->assertSessionHasErrors('values.photo');
        $this->post($url.'/submissions', ['values' => ['documents' => [$pdf('first.pdf'), $pdf('second.pdf')], 'photo' => $image()]])->assertSessionHasNoErrors();

        $submission = FormSubmission::sole();
        $documents = $submission->files()->where('field_key', 'documents')->orderBy('id')->get();
        $this->assertCount(2, $documents);
        $this->assertSame($documents->pluck('public_id')->all(), $submission->values_json['documents']);
        $this->assertSame($submission->files()->where('field_key', 'photo')->sole()->public_id, $submission->values_json['photo']);
        $this->get('/form-submissions/'.$submission->public_id)->assertOk()->assertSee('first.pdf')->assertSee('second.pdf')->assertSee('photo.png');
    }

    public function test_multiple_images_are_saved_individually_and_the_image_count_is_limited(): void
    {
        Storage::fake('local');
        $doctor = $this->doctor();
        $version = $this->template($doctor, [['key' => 'photos', 'type' => 'multiple_images', 'label' => 'Photos', 'required' => true]])->versions()->sole();
        $url = '/form-versions/'.$version->id.'/submissions';
        $image = static fn (string $name) => UploadedFile::fake()->create($name, 20, 'image/png');

        $this->actingAs($doctor->user)->get('/form-versions/'.$version->id)->assertOk()->assertSee('x-data="multipleImagePicker"', false);
        $this->post($url, ['values' => ['photos' => array_map(fn (int $index) => $image($index.'.png'), range(1, 11))]])->assertSessionHasErrors('values.photos');
        $this->post($url, ['values' => ['photos' => [$image('first.png'), $image('second.png')]]])->assertSessionHasNoErrors()->assertRedirect();

        $submission = FormSubmission::sole();
        $files = $submission->files()->orderBy('id')->get();
        $this->assertCount(2, $files);
        $this->assertSame($files->pluck('public_id')->all(), $submission->values_json['photos']);
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->path);
            $this->get(route('form-submission-files.show', $file))->assertDownload($file->original_name);
        }
    }

    public function test_doctor_can_upload_and_download_a_private_file_field(): void
    {
        Storage::fake('local');
        $doctor = $this->doctor();
        $template = $this->template($doctor, [
            ['key' => 'attachment', 'type' => 'file', 'label' => 'Attachment', 'required' => true],
        ]);
        $version = $template->versions()->sole();

        $this->actingAs($doctor->user)->get('/form-versions/'.$version->id)
            ->assertOk()
            ->assertSee('type="file"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->post('/form-versions/'.$version->id.'/submissions', [
            'values' => ['attachment' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $submission = FormSubmission::sole();
        $file = $submission->files()->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($file->public_id, $submission->values_json['attachment']);

        $this->get('/form-submissions/'.$submission->public_id)
            ->assertOk()
            ->assertSee('report.pdf');
        $this->get(route('form-submission-files.show', $file))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->post('/form-versions/'.$version->id.'/submissions', ['values' => []])
            ->assertSessionHasErrors('values.attachment');
        $this->post('/form-versions/'.$version->id.'/submissions', [
            'values' => ['attachment' => UploadedFile::fake()->create('malware.php', 10, 'text/x-php')],
        ])->assertSessionHasErrors('values.attachment');
        $this->assertDatabaseCount('form_submissions', 1);
    }
}
