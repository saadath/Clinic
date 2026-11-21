<?php

namespace App\Services\MedicalRecords;

use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\FormVersion;
use App\Models\MedicalEncounter;
use App\Models\MedicalRecordAmendment;
use App\Models\MedicalRecordEvent;
use App\Models\MedicalRecordFile;
use App\Models\Patient;
use App\Models\User;
use App\Services\Forms\ManageFormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageMedicalRecord
{
    public function __construct(private MedicalRecordAccess $access, private ClinicalHtml $html, private ManageFormSubmission $submissions) {}

    public function create(User $actor, Patient $patient, int $doctorId, ?string $note): MedicalEncounter
    {
        $doctor = $this->access->doctor($actor, $doctorId);
        $this->access->authorizePatient($actor, $patient);

        return DB::transaction(function () use ($actor, $patient, $doctor, $note): MedicalEncounter {
            $encounter = MedicalEncounter::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'created_by' => $actor->id,
                'note' => $note,
                'note_html' => $this->html->clean($note ?? ''),
                'note_language' => $doctor->chart_language ?? 'fa',
            ]);
            $encounter->forceFill(['status' => 'recorded'])->save();
            $this->event($encounter, $actor, 'created');

            return $encounter;
        });
    }

    public function updateNote(User $actor, MedicalEncounter $encounter, ?string $note): void
    {
        $body = $this->html->clean($note ?? '');

        DB::transaction(function () use ($actor, $encounter, $body): void {
            $encounter = MedicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            if ($encounter->note_html === $body) {
                return;
            }

            $this->event($encounter, $actor, 'updated', [
                'previous_note' => $encounter->note_html ?? $encounter->note,
                'previous_note_is_html' => $encounter->note_html !== null,
            ]);
            $encounter->update(['note_html' => $body]);
        });
    }

    public function delete(User $actor, MedicalEncounter $encounter): void
    {
        DB::transaction(function () use ($actor, $encounter): void {
            $encounter = MedicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $encounter->delete();
            $this->event($encounter, $actor, 'deleted');
        });
    }

    public function attachSubmission(User $actor, MedicalEncounter $encounter, FormVersion $version, array $values, callable $validate, array $uploads = []): FormSubmission
    {
        $this->access->authorizeEncounter($actor, $encounter, true);

        return DB::transaction(function () use ($actor, $encounter, $version, $values, $validate, $uploads): FormSubmission {
            $template = FormTemplate::whereKey($version->form_template_id)->lockForUpdate()->firstOrFail();
            $currentVersion = $template->versions()->orderByDesc('version_number')->firstOrFail();
            abort_unless($version->id === $currentVersion->id && $version->status === 'published' && $template->active, 422);
            abort_unless($template->owner_doctor_id === $encounter->doctor_id || $template->recipients()->whereKey($encounter->doctor_id)->exists(), 404);
            $version = $currentVersion;
            $validated = $validate($version, $values, $uploads);
            $normalized = $validated['values'];
            $encounter = MedicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $submission = $this->submissions->create($actor, $version, $encounter, $normalized, $uploads);
            $this->event($encounter, $actor, 'form_attached', ['form_version_id' => $version->id, 'submission_id' => $submission->id]);

            return $submission;
        });
    }

    public function amend(User $actor, MedicalEncounter $encounter, string $body): MedicalRecordAmendment
    {
        $this->access->authorizeEncounter($actor, $encounter, true);
        abort_unless($actor->hasRole('doctor') && $encounter->doctor->user_id === $actor->id, 403);

        return DB::transaction(function () use ($actor, $encounter, $body): MedicalRecordAmendment {
            $encounter = MedicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $amendment = $encounter->amendments()->create(['author_id' => $actor->id, 'body' => $body, 'created_at' => now()]);
            $this->event($encounter, $actor, 'amended', ['amendment_id' => $amendment->id]);

            return $amendment;
        });
    }

    public function upload(User $actor, MedicalEncounter $encounter, UploadedFile $file): MedicalRecordFile
    {
        $this->access->authorizeEncounter($actor, $encounter, true);
        $path = $file->store('medical-records/'.$encounter->public_id, 'local');
        abort_unless(is_string($path), 500, 'The medical record file could not be stored.');

        try {
            return DB::transaction(function () use ($actor, $encounter, $file, $path): MedicalRecordFile {
                $encounter = MedicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
                $record = $encounter->files()->create([
                    'uploaded_by' => $actor->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'created_at' => now(),
                ]);
                $this->event($encounter, $actor, 'file_added', ['file_id' => $record->id]);

                return $record;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function event(MedicalEncounter $encounter, User $actor, string $type, ?array $metadata = null): void
    {
        MedicalRecordEvent::create([
            'medical_encounter_id' => $encounter->id,
            'actor_id' => $actor->id,
            'event_type' => $type,
            'metadata_json' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
