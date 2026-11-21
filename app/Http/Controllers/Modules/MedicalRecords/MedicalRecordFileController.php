<?php

namespace App\Http\Controllers\Modules\MedicalRecords;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecordFile;
use App\Services\MedicalRecords\MedicalRecordAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalRecordFileController extends Controller
{
    public function show(Request $request, MedicalRecordFile $file, MedicalRecordAccess $access): StreamedResponse
    {
        $access->authorizeEncounter($request->user(), $file->encounter()->firstOrFail());
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, basename($file->original_name), [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
