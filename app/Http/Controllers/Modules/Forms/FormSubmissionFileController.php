<?php

namespace App\Http\Controllers\Modules\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormSubmissionFile;
use App\Services\Forms\FieldTypeRegistry;
use App\Services\Forms\FormAccess;
use App\Services\MedicalRecords\MedicalRecordAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionFileController extends Controller
{
    public function show(Request $request, FormSubmissionFile $file, FormAccess $forms, MedicalRecordAccess $records, FieldTypeRegistry $types): StreamedResponse
    {
        $submission = $file->submission()->with(['encounter', 'version.template.doctor'])->firstOrFail();
        $version = $submission->version;
        $template = $version->template;
        abort_if($submission->medical_encounter_id !== null && $submission->encounter === null, 404);
        if ($submission->encounter) {
            $records->authorizeEncounter($request->user(), $submission->encounter);
        } else {
            abort_unless(
                $submission->created_by === $request->user()->id
                    || $request->user()->can('forms.manage-all')
                    || $template->doctor->user_id === $request->user()->id,
                404,
            );
            $forms->authorize($request->user(), $template);
        }

        $schema = $forms->schemaFor($request->user(), $template, $version->schema_json);
        $field = collect($schema['sections'])->flatMap(fn (array $section) => $section['fields'])->firstWhere('key', $file->field_key);
        $value = $submission->values_json[$file->field_key] ?? null;
        abort_unless($field && $field['active'] && $types->get($field['type'])['kind'] === 'file' && in_array($file->public_id, (array) $value, true), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        $inline = $request->boolean('inline') && ($file->isImagePreview() || $file->isPdfPreview());

        return Storage::disk($file->disk)->response($file->path, basename($file->original_name), [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ], $inline ? 'inline' : 'attachment');
    }
}
