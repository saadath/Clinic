<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorStaffAssignment;
use App\Models\FormSubmission;
use App\Models\MedicalEncounter;
use App\Models\MedicalRecordFile;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Forms\ManageForm;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalRecordFeatureTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function account(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->sole());

        return $user;
    }

    /** @return array{Doctor, Patient} */
    private function clinicalContext(): array
    {
        $doctor = Doctor::factory()->create(['user_id' => $this->account('doctor')->id]);
        $patient = Patient::factory()->create();
        $patient->doctors()->attach($doctor, ['granted_by' => $doctor->user_id]);

        return [$doctor, $patient];
    }

    private function encounter(Doctor $doctor, Patient $patient): MedicalEncounter
    {
        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), ['doctor_id' => $doctor->id, 'note' => 'Original clinical note'])
            ->assertSessionHasNoErrors()->assertRedirect();

        return MedicalEncounter::where('patient_id', $patient->id)->latest('id')->firstOrFail();
    }

    public function test_encounter_is_linked_to_patient_and_actor_and_escapes_notes(): void
    {
        [$doctor, $patient] = $this->clinicalContext();

        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), ['doctor_id' => $doctor->id, 'note' => '<script>clinical</script>', 'created_by' => 999, 'status' => 'confirmed'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $encounter = MedicalEncounter::sole();
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'patient_id' => $patient->id, 'created_by' => $doctor->user_id, 'status' => 'recorded']);
        $this->assertDatabaseHas('medical_record_events', ['medical_encounter_id' => $encounter->id, 'actor_id' => $doctor->user_id, 'event_type' => 'created']);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertSee(route('medical-records.encounters', $patient), false)->assertDontSee('<script>clinical</script>', false);
        $this->get(route('patients.show', $patient))->assertRedirect(route('medical-records.index', $patient));
    }

    public function test_demographic_visibility_does_not_grant_clinical_access_and_unrelated_doctor_is_hidden(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);
        $outsider = $this->account('doctor');
        Doctor::factory()->create(['user_id' => $outsider->id]);

        $this->actingAs($outsider)->get(route('patients.show', $patient))->assertRedirect(route('medical-records.index', $patient));
        $this->get(route('medical-records.index', $patient))->assertOk()->assertDontSee('Original clinical note');
        $this->post('/medical-record/encounters/'.$encounter->public_id.'/confirm')->assertNotFound();
        $this->actingAs($this->account('general-receptionist'))->get(route('medical-records.index', $patient))->assertForbidden();
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'status' => 'recorded']);
    }

    public function test_assistant_access_expires_with_assignment_and_cannot_write_encounters(): void
    {
        $this->freezeTime();
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);
        $assistant = $this->account('doctor-assistant');
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $assistant->id, 'assignment_type' => 'assistant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour()]);

        $this->actingAs($assistant)->get(route('medical-records.index', $patient))->assertOk()->assertDontSee('Original clinical note');
        $this->post(route('medical-records.amendments.store', $encounter), ['body' => 'Restricted amendment'])->assertForbidden();
        $this->travel(2)->hours();
        $this->get(route('medical-records.index', $patient))->assertNotFound();
    }

    public function test_doctor_can_add_an_amendment_without_confirmation_and_original_note_is_preserved(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);

        $this->post(route('medical-records.amendments.store', $encounter), ['body' => 'Corrected history; reason: patient clarification'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'note' => 'Original clinical note', 'status' => 'recorded']);
        $this->assertDatabaseHas('medical_record_amendments', ['medical_encounter_id' => $encounter->id, 'author_id' => $doctor->user_id, 'body' => 'Corrected history; reason: patient clarification']);
        $this->assertDatabaseCount('medical_record_events', 2);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertDontSee('Original clinical note')->assertDontSee('Corrected history');
    }

    public function test_private_file_download_rechecks_access_and_encounter_accepts_multiple_uploads(): void
    {
        Storage::fake('local');
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);

        $this->post(route('medical-records.files.store', $encounter), ['file' => UploadedFile::fake()->createWithContent('report.txt', 'private clinical report')])->assertSessionHasNoErrors()->assertRedirect();
        $file = MedicalRecordFile::sole();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('medical-records.files.show', $file))->assertOk()->assertDownload('report.txt');
        $this->post(route('medical-records.files.store', $encounter), ['file' => UploadedFile::fake()->createWithContent('follow-up.txt', 'follow-up')])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('medical_record_files', 2);
        $this->actingAs($this->account('doctor'))->get(route('medical-records.files.show', $file))->assertNotFound();
        $this->actingAs($this->account('receptionist'))->get(route('medical-records.files.show', $file))->assertForbidden();
    }

    public function test_form_keeps_saved_schema_and_encounter_accepts_multiple_submissions(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);
        $manager = app(ManageForm::class);
        $template = $manager->create($doctor->user, $doctor->id, 'Clinical form', ['sections' => [['key' => 'main', 'title' => 'Main', 'columns' => 1, 'fields' => [['key' => 'symptoms', 'type' => 'long_text', 'label' => 'Original symptoms', 'required' => true]]]]]);
        $version = $template->versions()->sole();

        $this->get(route('medical-records.forms.show', [$encounter, $version]))->assertOk()->assertDontSee(__('forms.sample_notice'));
        $this->post(route('medical-records.forms.store', [$encounter, $version]), ['values' => ['symptoms' => 'Recorded symptoms']])->assertSessionHasNoErrors()->assertRedirect();
        $submission = FormSubmission::sole();
        $this->assertDatabaseHas('form_submissions', ['id' => $submission->id, 'medical_encounter_id' => $encounter->id, 'form_version_id' => $version->id, 'created_by' => $doctor->user_id]);
        $this->post(route('medical-records.forms.store', [$encounter, $version]), ['values' => ['symptoms' => 'Follow-up symptoms']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 2);
        $this->get(route('form-submissions.show', $submission))->assertOk()->assertSee('Original symptoms')->assertSee('Recorded symptoms');
        $this->assertDatabaseHas('form_submissions', ['id' => $submission->id, 'medical_encounter_id' => $encounter->id, 'created_by' => $doctor->user_id]);
        $this->get(route('forms.edit', $template))->assertRedirect();
        $schema = $version->fresh()->schema_json;
        $schema['sections'][0]['fields'][0]['label'] = 'Changed symptoms';
        $next = $manager->save($doctor->user, $version->fresh(), $schema, 1);
        $this->assertSame(2, $next->version_number);
        $this->get(route('form-submissions.show', $submission))->assertOk()->assertSee('Original symptoms')->assertDontSee('Changed symptoms');
        $this->actingAs($this->account('doctor'))->get(route('form-submissions.show', $submission))->assertNotFound();
    }

    public function test_foreign_doctor_cannot_be_attached_and_guest_is_redirected(): void
    {
        [$doctor, $patient] = $this->clinicalContext();

        $this->get(route('medical-records.index', $patient))->assertRedirect(route('login'));
        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), ['doctor_id' => 999])->assertRedirect();
        $this->assertDatabaseHas('medical_encounters', ['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
    }

    public function test_doctor_can_open_medical_record_from_patient_details_and_start_first_encounter_without_prior_link(): void
    {
        $doctor = Doctor::factory()->create(['user_id' => $this->account('doctor')->id]);
        $patient = Patient::factory()->create();

        $patient->update(['national_id' => '0012345678', 'mobile' => '09121234567']);
        $this->actingAs($doctor->user)->get(route('patients.index'))->assertOk()->assertSee(route('patients.show', $patient), false)
            ->assertSee('۰۰۱۲۳۴۵۶۷۸')->assertSee('۰۹۱۲۱۲۳۴۵۶۷')->assertDontSee('0012345678')->assertDontSee('09121234567');
        $this->get(route('doctor-settings.edit'))->assertOk()->assertSee('name="chronic_summary_font_size" min="8" max="26"', false)
            ->assertSee('name="theme"', false)->assertSee('<option value="dark"', false)->assertSee('<option value="light"', false);
        $this->get(route('patients.show', $patient))->assertRedirect(route('medical-records.index', $patient));
        $this->get(route('medical-records.index', $patient))->assertOk()->assertSee('۰۰۱۲۳۴۵۶۷۸')->assertSee('۰۹۱۲۱۲۳۴۵۶۷')->assertSee(__('patients.mobile'))->assertDontSee(__('patients.sex'))->assertDontSee('0012345678')->assertDontSee('09121234567')->assertDontSee('name="doctor_id"', false);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertSee(route('medical-records.create', $patient), false)->assertDontSee('name="note"', false);
        $this->get(route('medical-records.create', $patient))
            ->assertOk()
            ->assertSee('data-clinical-editor dir="rtl"', false)
            ->assertSee('name="note"', false)
            ->assertSee('data-direction="rtl"', false)
            ->assertSee('data-direction="ltr"', false)
            ->assertSee('aria-label="تراز راست"', false)
            ->assertSee('aria-label="تراز وسط"', false)
            ->assertSee('aria-label="تراز چپ"', false)
            ->assertSee('aria-label="واگرد"', false)
            ->assertSee('<svg', false)
            ->assertDontSee(__('forms.encounter_forms'));
        $this->post(route('medical-records.store', $patient), ['doctor_id' => $doctor->id, 'note' => 'First encounter from patient list'])->assertSessionHasNoErrors()->assertRedirect(route('medical-records.index', $patient));

        $this->assertDatabaseHas('medical_encounters', ['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'created_by' => $doctor->user_id, 'note' => 'First encounter from patient list']);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertDontSee('First encounter from patient list');
        $this->assertDatabaseHas('medical_encounters', ['patient_id' => $patient->id, 'note' => 'First encounter from patient list']);
    }

    public function test_patient_live_search_returns_only_matching_visible_patients_for_persian_digit_queries(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $patient->update(['national_id' => '0012345678', 'mobile' => '09121234567']);
        $unrelatedPatient = Patient::factory()->create(['first_name' => 'Unrelated']);
        $unrelatedPatient->doctors()->attach($doctor, ['granted_by' => $doctor->user_id]);

        $response = $this->actingAs($doctor->user)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('patients.index', ['q' => '۰۹۱۲۱۲۳۴۵۶۷']));

        $response->assertOk()->assertSee('۰۰۱۲۳۴۵۶۷۸')->assertDontSee('Unrelated')->assertDontSee('<!DOCTYPE html>', false);
        $response->assertSee(route('patients.edit', $patient), false);
    }

    public function test_invalid_upload_is_rejected_and_encounter_confirmation_endpoint_is_removed(): void
    {
        Storage::fake('local');
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);

        $this->post(route('medical-records.files.store', $encounter), ['file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')])->assertSessionHasErrors('file');
        Storage::disk('local')->assertDirectoryEmpty('medical-records');
        $this->assertDatabaseCount('medical_record_files', 0);
        $this->actingAs($this->account('clinic-administrator'))->get(route('medical-records.index', $patient))->assertOk();
        $this->post('/medical-record/encounters/'.$encounter->public_id.'/confirm')->assertNotFound();
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'status' => 'recorded']);
    }

    public function test_doctor_writing_language_is_private_persistent_and_independent_of_ui_and_html_is_sanitized(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $this->actingAs($doctor->user)->put(route('doctor-settings.update'), ['chart_language' => 'en', 'chronic_summary_font_size' => 22, 'theme' => 'dark'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'chart_language' => 'en', 'chronic_summary_font_size' => 22]);
        $this->assertSame('dark', $doctor->user->fresh()->theme);
        $this->assertSame('fa', $doctor->user->fresh()->locale);
        $this->get(route('doctor-settings.edit'))->assertOk()
            ->assertSee('name="chronic_summary_font_size" min="8" max="26"', false)
            ->assertSee('name="theme"', false)->assertSee('<option value="dark" selected', false);
        $this->put(route('doctor-settings.update'), ['chart_language' => 'fa', 'chronic_summary_font_size' => 27, 'theme' => 'light'])->assertSessionHasErrors('chronic_summary_font_size');
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'chart_language' => 'en', 'chronic_summary_font_size' => 22]);
        $this->assertSame('dark', $doctor->user->fresh()->theme);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertSee('data-clinical-editor dir="ltr"', false)->assertDontSee('name="note"', false)->assertSee('<html lang="fa" dir="rtl"', false);
        $this->get(route('medical-records.create', $patient))->assertOk()->assertSee('data-clinical-editor dir="ltr"', false)->assertSee('<html lang="fa" dir="rtl"', false);
        $this->post(route('medical-records.store', $patient), ['note' => '<h2 dir="ltr">History</h2><p dir="rtl" onclick="alert(1)"><strong>Safe</strong><script>alert(1)</script><a href="javascript:alert(1)">link</a></p>'])->assertRedirect();
        $encounter = MedicalEncounter::sole();
        $this->assertStringContainsString('<strong>Safe</strong>', $encounter->note_html);
        $this->assertStringNotContainsString('onclick', $encounter->note_html);
        $this->assertStringNotContainsString('javascript:', $encounter->note_html);
        $this->assertStringNotContainsString('<script', $encounter->note_html);
        $this->assertStringContainsString('<h2 dir="ltr">History</h2>', $encounter->note_html);
        $this->assertStringContainsString('<p dir="rtl">', $encounter->note_html);
        $this->assertSame('en', $encounter->note_language);
        $this->post(route('medical-records.summary', $patient), ['summary' => '<p>Diabetes</p>'])->assertRedirect();
        $this->post(route('medical-records.summary', $patient), ['summary' => '<p>Updated diabetes history</p>'])->assertRedirect();
        $this->assertDatabaseCount('patient_chronic_summaries', 2);
        $this->get(route('medical-records.index', $patient))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('style="font-size: 22px"', false)
            ->assertSee('Updated diabetes history')
            ->assertSee('aria-label="Undo"', false)
            ->assertSee('x-data="chronicSummary"', false)
            ->assertSee('@click="editing = true"', false)
            ->assertSee('x-cloak x-show="editing"', false)
            ->assertSee('@submit.prevent="save($event.currentTarget)"', false)
            ->assertDontSee('<details>', false);
        $this->actingAs($this->account('general-receptionist'))->get(route('doctor-settings.edit'))->assertForbidden();
        $this->put(route('doctor-settings.update'), ['chart_language' => 'fa', 'chronic_summary_font_size' => 14, 'theme' => 'light'])->assertForbidden();
        $this->get(route('patients.index'))->assertOk()->assertDontSee(route('doctor-settings.edit'), false);
    }

    public function test_chronic_summary_can_be_saved_with_a_json_response(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $url = route('medical-records.summary', $patient);
        $body = '<p><strong>Updated history</strong></p>';

        $this->actingAs($doctor->user)->postJson($url, [
            'summary' => '<p class="discard"><strong>Updated history</strong></p>',
        ])->assertOk()
            ->assertJsonPath('html', $body)
            ->assertJsonPath('direction', $doctor->chart_language === 'en' ? 'ltr' : 'rtl');

        $this->assertDatabaseHas('patient_chronic_summaries', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'body_html' => $body,
        ]);

        $this->postJson($url, ['summary' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('summary');
        $this->actingAs($this->account('general-receptionist'))->postJson($url, ['summary' => 'Not allowed'])->assertForbidden();
        $this->assertDatabaseCount('patient_chronic_summaries', 1);
    }

    public function test_create_encounter_page_requires_a_doctor_with_record_access(): void
    {
        [$doctor, $patient] = $this->clinicalContext();

        $this->get(route('medical-records.create', $patient))->assertRedirect(route('login'));
        $this->actingAs($this->account('general-receptionist'))->get(route('medical-records.create', $patient))->assertForbidden();
        $assistant = $this->account('doctor-assistant');
        DoctorStaffAssignment::factory()->create([
            'doctor_id' => $doctor->id,
            'staff_user_id' => $assistant->id,
            'assignment_type' => 'assistant',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addHour(),
        ]);
        $this->actingAs($assistant)->get(route('medical-records.create', $patient))->assertForbidden();
        $this->actingAs($doctor->user)->get(route('medical-records.create', $patient))->assertOk();
    }

    public function test_doctor_can_complete_a_saved_form_in_the_encounter_creation_page(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Visit intake', [
            'sections' => [[
                'key' => 'main',
                'title' => 'Visit details',
                'columns' => 1,
                'fields' => [['key' => 'symptoms', 'type' => 'long_text', 'label' => 'Current symptoms', 'required' => true]],
            ]],
        ]);
        $version = $template->versions()->sole();
        $secondaryTemplate = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Vitals', [
            'sections' => [[
                'key' => 'vitals',
                'title' => 'Vitals',
                'columns' => 1,
                'fields' => [['key' => 'temperature', 'type' => 'decimal', 'label' => 'Temperature']],
            ]],
        ]);
        $secondaryVersion = $secondaryTemplate->versions()->sole();

        $this->actingAs($doctor->user)->get(route('medical-records.create', $patient))
            ->assertOk()
            ->assertDontSee(__('forms.encounter_forms'))
            ->assertSee('Visit intake')
            ->assertSee('Vitals')
            ->assertSee('Current symptoms')
            ->assertSee('values['.$version->id.'][symptoms]', false)
            ->assertSee('values['.$secondaryVersion->id.'][temperature]', false)
            ->assertDontSee('form_version_ids', false)
            ->assertDontSee(__('forms.include_in_encounter'));

        $this->post(route('medical-records.store', $patient), [
            'note' => 'Encounter note',
            'values' => [
                $version->id => ['symptoms' => 'Headache and fever'],
                $secondaryVersion->id => ['temperature' => '37.5'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('medical-records.index', $patient));

        $encounter = MedicalEncounter::sole();
        $this->assertDatabaseHas('medical_encounters', [
            'id' => $encounter->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'note' => 'Encounter note',
        ]);
        $this->assertDatabaseHas('form_submissions', [
            'form_version_id' => $version->id,
            'medical_encounter_id' => $encounter->id,
            'created_by' => $doctor->user_id,
            'values_json' => json_encode(['symptoms' => 'Headache and fever']),
        ]);
        $this->assertDatabaseHas('form_submissions', [
            'form_version_id' => $secondaryVersion->id,
            'medical_encounter_id' => $encounter->id,
            'values_json' => json_encode(['temperature' => 37.5]),
        ]);

        $this->post(route('medical-records.store', $patient), [
            'note' => 'Follow-up encounter note',
            'values' => [
                $version->id => ['symptoms' => 'Cough during follow-up'],
                $secondaryVersion->id => ['temperature' => '38'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('medical-records.index', $patient));

        $this->assertDatabaseCount('medical_encounters', 2);
        $this->assertDatabaseCount('form_submissions', 4);
        $this->assertDatabaseHas('form_submissions', [
            'form_version_id' => $version->id,
            'values_json' => json_encode(['symptoms' => 'Cough during follow-up']),
        ]);
        $this->assertDatabaseHas('form_submissions', [
            'form_version_id' => $secondaryVersion->id,
            'values_json' => json_encode(['temperature' => 38.0]),
        ]);
    }

    public function test_file_field_uploads_with_encounter_and_requires_encounter_access_to_download(): void
    {
        Storage::fake('local');
        [$doctor, $patient] = $this->clinicalContext();
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Referral', [
            'sections' => [[
                'key' => 'main',
                'title' => 'Referral',
                'columns' => 1,
                'fields' => [['key' => 'document', 'type' => 'pdf', 'label' => 'Referral document', 'required' => true]],
            ]],
        ]);
        $version = $template->versions()->sole();

        $this->actingAs($doctor->user)->get(route('medical-records.create', $patient))
            ->assertOk()
            ->assertSee('type="file"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('medical-records.store', $patient), [
            'note' => 'Referral visit',
            'values' => [$version->id => ['document' => UploadedFile::fake()->create('referral.pdf', 30, 'application/pdf')]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('medical-records.index', $patient));

        $submission = FormSubmission::sole();
        $file = $submission->files()->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->assertNotNull($submission->medical_encounter_id);
        $this->actingAs($doctor->user)->get(route('form-submission-files.show', $file))->assertOk();

        $outsider = $this->account('doctor');
        Doctor::factory()->create(['user_id' => $outsider->id]);
        $this->actingAs($outsider)->get(route('form-submission-files.show', $file))->assertNotFound();
    }

    public function test_encounter_card_summarizes_saved_fields_and_previews_only_visible_attachments(): void
    {
        Storage::fake('local');
        [$doctor, $patient] = $this->clinicalContext();
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Visit form', [
            'sections' => [[
                'key' => 'main', 'title' => 'Visit', 'columns' => 1,
                'fields' => [
                    ['key' => 'reason', 'type' => 'checkbox', 'label' => 'Reason', 'options' => ['Routine', 'After surgery', 'Consultation']],
                    ['key' => 'comment', 'type' => 'short_text', 'label' => 'Comment'],
                    ['key' => 'flag', 'type' => 'single_checkbox', 'label' => 'Flag'],
                    ['key' => 'photos', 'type' => 'multiple_images', 'label' => 'Photos'],
                    ['key' => 'report', 'type' => 'pdf', 'label' => 'Report'],
                    ['key' => 'private_note', 'type' => 'short_text', 'label' => 'Private note', 'confidentiality' => 'confidential'],
                    ['key' => 'secret', 'type' => 'pdf', 'label' => 'Confidential report', 'confidentiality' => 'confidential'],
                ],
            ]],
        ]);
        $version = $template->versions()->sole();

        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), ['values' => [$version->id => [
            'reason' => ['After surgery', 'Consultation'], 'comment' => '<b>plain text</b>', 'flag' => '0',
            'photos' => [UploadedFile::fake()->create('first.jpg', 20, 'image/jpeg'), UploadedFile::fake()->create('second.png', 20, 'image/png')],
            'report' => UploadedFile::fake()->create('visible.pdf', 20, 'application/pdf'),
            'private_note' => 'Private answer', 'secret' => UploadedFile::fake()->create('private.pdf', 20, 'application/pdf'),
        ]]])->assertSessionHasNoErrors();

        $submission = FormSubmission::sole();
        $list = route('medical-records.encounters', $patient);
        $page = $this->get($list)->assertOk()->assertDontSee('Visit form')->assertSee('After surgery - Consultation')->assertDontSee('Routine')
            ->assertSee('&lt;b&gt;plain text&lt;/b&gt;', false)->assertSee('Private answer')->assertSee(__('medical_records.answer_unchecked'));
        foreach ($submission->files as $file) {
            $url = route('form-submission-files.show', ['file' => $file, 'inline' => 1]);
            $page->assertSee($url, false)->assertSee($file->original_name);
            $this->get($url)->assertOk()->assertHeader('Content-Type', $file->mime_type)
                ->assertHeader('Content-Disposition', 'inline; filename='.$file->original_name);
        }

        $assistant = $this->account('doctor-assistant');
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $assistant->id, 'assignment_type' => 'assistant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour()]);
        $privateFile = $submission->files->firstWhere('field_key', 'secret');
        $this->actingAs($assistant)->get($list)->assertOk()->assertSee('After surgery - Consultation')->assertSee('first.jpg')
            ->assertDontSee('Private answer')->assertDontSee('Confidential report')->assertDontSee('private.pdf')->assertDontSee($privateFile->public_id);
        $this->get(route('form-submission-files.show', ['file' => $privateFile, 'inline' => 1]))->assertNotFound();
    }

    public function test_medical_record_report_is_printable_and_includes_only_visible_exportable_clinical_content(): void
    {
        Storage::fake('local');
        $this->travelTo('2025-01-15 12:00:00');
        [$doctor, $patient] = $this->clinicalContext();
        $patient->update(['address' => 'Example address', 'mobile' => '09121234567', 'national_id' => '0012345678']);
        $this->encounter($doctor, $patient);
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Assessment form', [
            'sections' => [[
                'key' => 'main', 'title' => 'Assessment', 'columns' => 1,
                'fields' => [
                    ['key' => 'text', 'type' => 'short_text', 'label' => 'Assessment'],
                    ['key' => 'no_export', 'type' => 'short_text', 'label' => 'Internal', 'exportable' => false],
                    ['key' => 'attachment', 'type' => 'pdf', 'label' => 'Attachment'],
                ],
            ]],
        ]);
        $version = $template->versions()->sole();
        $this->put(route('doctor-settings.update'), ['chart_language' => 'en', 'chronic_summary_font_size' => 14, 'theme' => 'light'])->assertSessionHasNoErrors();
        $this->post(route('medical-records.summary', $patient), ['summary' => '<p><strong>Latest chronic summary</strong></p>'])->assertSessionHasNoErrors();
        $this->post(route('medical-records.store', $patient), ['note' => '<p>English encounter note</p>', 'values' => [$version->id => [
            'text' => 'Saved assessment', 'no_export' => 'Internal form content',
            'attachment' => UploadedFile::fake()->create('private-report.pdf', 20, 'application/pdf'),
        ]]])->assertSessionHasNoErrors();
        $encounter = $patient->medicalEncounters()->latest('id')->firstOrFail();
        $this->post(route('medical-records.amendments.store', $encounter), ['body' => 'Later clarification'])->assertSessionHasNoErrors();

        $url = route('medical-records.report', $patient);
        $pdfUrl = route('medical-records.report', ['patient' => $patient, 'format' => 'pdf']);
        $this->get(route('medical-records.index', $patient))->assertOk()->assertSee($pdfUrl, false)->assertSee($url.'?format=html', false);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('size: A4 portrait', false)->assertSee('table-header-group', false)->assertSee('Vazirmatn Variable')
            ->assertSee('Example address')->assertSee('۱۴۰۳/۱۰/۲۶')
            ->assertSee('<dt>'.__('patients.national_id').'</dt><dd><bdi dir="ltr">۰۰۱۲۳۴۵۶۷۸</bdi></dd>', false)
            ->assertSee('<dt>'.__('patients.mobile').'</dt><dd><bdi dir="ltr">۰۹۱۲۱۲۳۴۵۶۷</bdi></dd>', false)
            ->assertSee('<strong dir="auto">'.e($doctor->user->name).'</strong>', false)
            ->assertDontSee('</bdi><bdi>'.e($doctor->user->name).'</bdi></h3>', false)->assertDontSee('Assessment form')
            ->assertSee($doctor->medical_council_number)->assertSee('lang="en" dir="ltr"', false)
            ->assertSee('Latest chronic summary')->assertSee('Original clinical note')->assertSee('English encounter note')
            ->assertSee('Saved assessment')->assertSee('Later clarification')
            ->assertDontSee('Internal form content')->assertDontSee('private-report.pdf');
        $this->get($pdfUrl)->assertOk()->assertSee('data-print-report="1"', false)->assertSee('Saved assessment');

        $outsider = $this->account('doctor');
        Doctor::factory()->create(['user_id' => $outsider->id]);
        $this->actingAs($outsider)->get($pdfUrl)->assertOk()->assertSee('Example address')
            ->assertDontSee('Latest chronic summary')->assertDontSee('Original clinical note')
            ->assertDontSee('English encounter note')->assertDontSee('Saved assessment')->assertDontSee('Later clarification');
        $this->actingAs($this->account('general-receptionist'))->get($pdfUrl)->assertForbidden();
    }

    public function test_encounter_summary_displays_saved_text_choices_and_slider_values(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Assessment', [
            'sections' => [[
                'key' => 'main', 'title' => 'Assessment', 'columns' => 1,
                'fields' => [
                    ['key' => 'subject', 'type' => 'short_text', 'label' => 'Subject'],
                    ['key' => 'details', 'type' => 'long_text', 'label' => 'Details'],
                    ['key' => 'guidance', 'type' => 'rich_text', 'label' => 'Guidance'],
                    ['key' => 'kind', 'type' => 'radio', 'label' => 'Visit type', 'options' => ['Follow-up', 'Unselected radio']],
                    ['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['Stable', 'Unselected status']],
                    ['key' => 'actions', 'type' => 'multi_select', 'label' => 'Actions', 'options' => ['Review', 'Monitor', 'Unselected action']],
                    ['key' => 'pain', 'type' => 'slider', 'label' => 'Pain score', 'min' => 0, 'max' => 10, 'unit' => 'points'],
                ],
            ]],
        ]);
        $version = $template->versions()->sole();

        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), ['values' => [$version->id => [
            'subject' => 'Routine assessment', 'details' => "First line\nSecond line", 'guidance' => '<strong>Rest</strong>',
            'kind' => 'Follow-up', 'status' => 'Stable', 'actions' => ['Review', 'Monitor'], 'pain' => '0',
        ]]])->assertSessionHasNoErrors();

        $page = $this->get(route('medical-records.encounters', $patient))->assertOk()
            ->assertDontSee('Unselected radio')->assertDontSee('Unselected status')->assertDontSee('Unselected action');
        foreach (['Subject' => 'Routine assessment', 'Details' => "First line\nSecond line", 'Guidance' => 'Rest', 'Visit type' => 'Follow-up', 'Status' => 'Stable', 'Actions' => 'Review - Monitor', 'Pain score' => '0'] as $label => $answer) {
            $this->assertMatchesRegularExpression('~<dt[^>]*>'.preg_quote($label, '~').':</dt>\s*<dd[^>]*>'.preg_quote($answer, '~').'</dd>~', $page->getContent());
        }
    }

    public function test_encounters_page_lists_visible_notes_in_cards_with_persian_dates(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $this->travelTo('2025-01-15 12:00:00');
        $this->encounter($doctor, $patient);
        $this->post(route('medical-records.store', $patient), ['note' => '<p>Second <strong>visit</strong></p>'])->assertRedirect();

        $response = $this->get(route('medical-records.encounters', $patient));

        $response->assertOk()
            ->assertSee(__('medical_records.encounters_title'))
            ->assertSee('Second')
            ->assertSee('visit')
            ->assertSee('۱۴۰۳/۱۰/۲۶')
            ->assertSee('<article', false)
            ->assertDontSee('در انتظار تأیید')
            ->assertDontSee('تأییدشده');
    }

    public function test_doctor_can_edit_encounter_note_and_the_previous_note_is_retained(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);
        $list = route('medical-records.encounters', $patient);
        $edit = route('medical-records.edit', $encounter);
        $update = route('medical-records.update', $encounter);

        $this->get($list)->assertOk()->assertSee($edit, false)->assertSee(route('medical-records.destroy', $encounter), false);
        $this->get($edit)->assertOk()->assertSee('Original clinical note');
        $this->from($edit)->patch($update, ['note' => ['invalid']])->assertRedirect($edit)->assertSessionHasErrors('note');
        $this->patch($update, ['note' => '<p class="ignored"><strong>Updated note</strong></p>', 'patient_id' => 0])
            ->assertSessionHasNoErrors()->assertRedirect($list.'#encounter-'.$encounter->public_id);

        $this->get($list)->assertOk()->assertSee('<p><strong>Updated note</strong></p>', false)->assertDontSee('Original clinical note');
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'patient_id' => $patient->id, 'note_html' => '<p><strong>Updated note</strong></p>']);
        $revision = $encounter->events()->where('event_type', 'updated')->sole();
        $this->assertSame('Original clinical note', $revision->metadata_json['previous_note']);
        $this->assertSame($doctor->user_id, $revision->actor_id);
    }

    public function test_assistant_cannot_edit_or_delete_a_visible_encounter(): void
    {
        [$doctor, $patient] = $this->clinicalContext();
        $encounter = $this->encounter($doctor, $patient);
        $assistant = $this->account('doctor-assistant');
        DoctorStaffAssignment::factory()->create(['doctor_id' => $doctor->id, 'staff_user_id' => $assistant->id, 'assignment_type' => 'assistant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour()]);
        $edit = route('medical-records.edit', $encounter);
        $update = route('medical-records.update', $encounter);
        $delete = route('medical-records.destroy', $encounter);

        $this->actingAs($assistant)->get(route('medical-records.encounters', $patient))->assertOk()->assertSee('Original clinical note')->assertDontSee($edit, false)->assertDontSee($delete, false);
        $this->get($edit)->assertForbidden();
        $this->patch($update, ['note' => 'Not allowed'])->assertForbidden();
        $this->delete($delete)->assertForbidden();

        $outsider = $this->account('doctor');
        Doctor::factory()->create(['user_id' => $outsider->id]);
        $this->actingAs($outsider)->patch($update, ['note' => 'Not allowed'])->assertNotFound();
        $this->delete($delete)->assertNotFound();

        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'note' => 'Original clinical note', 'deleted_at' => null]);
    }

    public function test_doctor_can_delete_an_encounter_and_its_private_files_remain_stored(): void
    {
        Storage::fake('local');
        [$doctor, $patient] = $this->clinicalContext();
        $template = app(ManageForm::class)->create($doctor->user, $doctor->id, 'Referral', [
            'sections' => [[
                'key' => 'main', 'title' => 'Referral', 'columns' => 1,
                'fields' => [['key' => 'document', 'type' => 'pdf', 'label' => 'Referral document']],
            ]],
        ]);
        $version = $template->versions()->sole();
        $this->actingAs($doctor->user)->post(route('medical-records.store', $patient), [
            'note' => 'Visit to remove',
            'values' => [$version->id => ['document' => UploadedFile::fake()->create('referral.pdf', 30, 'application/pdf')]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $encounter = MedicalEncounter::sole();
        $this->post(route('medical-records.files.store', $encounter), ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'Visit notes')])->assertSessionHasNoErrors();
        $submission = $encounter->submissions()->sole();
        $formFile = $submission->files()->sole();
        $recordFile = $encounter->files()->sole();
        $otherEncounter = $this->encounter($doctor, $patient);
        $list = route('medical-records.encounters', $patient);

        $this->delete(route('medical-records.destroy', $encounter))->assertRedirect($list);

        $this->get($list)->assertOk()->assertDontSee('Visit to remove')->assertSee('encounter-'.$otherEncounter->public_id);
        $this->get(route('medical-records.edit', $encounter))->assertNotFound();
        $this->get(route('form-submissions.show', $submission))->assertNotFound();
        $this->get(route('form-submission-files.show', $formFile))->assertNotFound();
        $this->get(route('medical-records.files.show', $recordFile))->assertNotFound();
        $this->assertSoftDeleted($encounter);
        $this->assertModelExists($submission);
        Storage::disk('local')->assertExists([$formFile->path, $recordFile->path]);
        $this->assertDatabaseHas('medical_record_events', ['medical_encounter_id' => $encounter->id, 'actor_id' => $doctor->user_id, 'event_type' => 'deleted']);
    }
}
