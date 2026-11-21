<?php

namespace App\Services\Forms;

use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\MedicalEncounter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageFormSubmission
{
    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $uploads
     * @param  array<string, mixed>  $values
     */
    public function create(User $actor, FormVersion $version, ?MedicalEncounter $encounter, array $values, array $uploads = []): FormSubmission
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($actor, $version, $encounter, $values, $uploads, &$storedPaths): FormSubmission {
                $submission = FormSubmission::create([
                    'form_version_id' => $version->id,
                    'medical_encounter_id' => $encounter?->id,
                    'values_json' => $values,
                    'created_by' => $actor->id,
                ]);
                $values = $this->storeFiles($submission, $uploads, $values, $storedPaths);
                $submission->forceFill(['values_json' => $values])->save();

                return $submission;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $uploads
     * @param  array<string, mixed>  $values
     * @param  list<string>  $storedPaths
     * @return array<string, mixed>
     */
    public function storeFiles(FormSubmission $submission, array $uploads, array $values, array &$storedPaths): array
    {
        if ($uploads === []) {
            return $values;
        }

        foreach ($uploads as $key => $upload) {
            $multiple = is_array($values[$key]);
            $files = $upload === null ? [] : (is_array($upload) ? array_values($upload) : [$upload]);
            $publicIds = [];
            foreach ($files as $file) {
                abort_unless($file instanceof UploadedFile && $file->isValid(), 422);
                $path = $file->store('form-submissions/'.$submission->public_id, 'local');
                abort_unless(is_string($path), 500, 'The form submission file could not be stored.');
                $storedPaths[] = $path;
                $record = $submission->files()->create([
                    'field_key' => $key,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                ]);
                $publicIds[] = $record->public_id;
            }
            $values[$key] = $multiple ? $publicIds : ($publicIds[0] ?? null);
        }

        return $values;
    }
}
