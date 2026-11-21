<?php

namespace App\Http\Controllers\Modules\MedicalRecords;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\FormVersion;
use App\Models\MedicalEncounter;
use App\Models\Patient;
use App\Services\Forms\FormAccess;
use App\Services\Forms\FormRuntime;
use App\Services\Forms\FormSubmissionSummary;
use App\Services\MedicalRecords\ClinicalHtml;
use App\Services\MedicalRecords\ManageMedicalRecord;
use App\Services\MedicalRecords\MedicalRecordAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MedicalRecordController extends Controller
{
    public function index(Request $request, Patient $patient, MedicalRecordAccess $access): View
    {
        Gate::authorize('medical-records.view');
        $access->authorizePatient($request->user(), $patient);
        $chartDoctor = $request->user()->hasRole('doctor') ? $request->user()->doctor()->where('active', true)->first() : null;
        $chartLanguage = $chartDoctor?->chart_language ?? 'fa';
        $chronicSummaryFontSize = $chartDoctor?->chronic_summary_font_size ?? 14;
        $summary = $chartDoctor ? DB::table('patient_chronic_summaries')->where('patient_id', $patient->id)->where('doctor_id', $chartDoctor->id)->latest('id')->first() : null;

        return view('medical-records.index', compact('patient', 'chartDoctor', 'chartLanguage', 'chronicSummaryFontSize', 'summary'));
    }

    public function create(Request $request, Patient $patient, MedicalRecordAccess $access, FormAccess $formAccess): View
    {
        Gate::authorize('medical-records.view');
        $access->authorizePatient($request->user(), $patient);
        Gate::authorize('medical-records.manage');
        abort_unless($request->user()->hasRole('doctor'), 403);

        $doctor = $request->user()->doctor()->where('active', true)->firstOrFail();
        $forms = $this->publishedFormVersions($doctor)->map(fn (FormVersion $version): array => [
            'version' => $version,
            'schema' => $formAccess->schemaFor($request->user(), $version->template, $version->schema_json),
        ]);

        return view('medical-records.create', [
            'patient' => $patient,
            'chartLanguage' => $doctor->chart_language,
            'forms' => $forms,
        ]);
    }

    public function report(Request $request, Patient $patient, MedicalRecordAccess $access, FormSubmissionSummary $formSummary, ClinicalHtml $html): Response
    {
        $user = $request->user();
        $access->authorizePatient($user, $patient);
        $chartDoctor = $user->hasRole('doctor') ? $user->doctor()->where('active', true)->with('user')->first() : null;
        $summary = $chartDoctor ? DB::table('patient_chronic_summaries')->where('patient_id', $patient->id)->where('doctor_id', $chartDoctor->id)->latest('id')->first() : null;
        $encounters = $access->encounters($user)->whereBelongsTo($patient)
            ->with(['doctor.user', 'amendments.author', 'submissions.version.template.doctor'])
            ->latest()->orderByDesc('id')->get();

        $visits = $encounters->map(fn (MedicalEncounter $encounter) => [
            'encounter' => $encounter,
            'noteHtml' => $encounter->note_html === null ? null : $html->clean($encounter->note_html),
            'forms' => $encounter->submissions->map(fn (FormSubmission $submission) => [
                'title' => $submission->version->template->name,
                'fields' => $formSummary->fields($user, $submission, forExport: true),
            ])->filter(fn (array $form) => $form['fields'] !== []),
        ]);
        $doctors = $encounters->pluck('doctor')->unique('id');
        if ($chartDoctor && ($doctors->isEmpty() || ($summary && ! $doctors->contains('id', $chartDoctor->id)))) {
            $doctors->push($chartDoctor);
        }

        return response()->view('medical-records.report', [
            'patient' => $patient,
            'summaryHtml' => $summary ? $html->clean($summary->body_html) : '',
            'summaryLanguage' => $summary?->language ?? 'fa',
            'visits' => $visits,
            'doctors' => $doctors,
            'issuedOn' => now('Asia/Tehran'),
            'print' => $request->query('format') === 'pdf',
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function encounters(Request $request, Patient $patient, MedicalRecordAccess $access, FormSubmissionSummary $summary): View
    {
        Gate::authorize('medical-records.view');
        $access->authorizePatient($request->user(), $patient);
        $encounters = $patient->medicalEncounters()
            ->whereIn('doctor_id', $access->encounters($request->user())->select('doctor_id'))
            ->with(['doctor', 'submissions.version.template.doctor', 'submissions.files'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $canCreate = $request->user()->can('medical-records.manage')
            && $request->user()->hasRole('doctor')
            && $request->user()->doctor()->where('active', true)->exists();

        $manageableEncounters = $encounters->filter(fn (MedicalEncounter $encounter) => $access->canManageEncounter($request->user(), $encounter))->modelKeys();

        $formSummaries = $encounters->mapWithKeys(fn (MedicalEncounter $encounter) => [
            $encounter->id => $encounter->submissions->map(fn (FormSubmission $submission) => [
                'title' => $submission->version->template->name,
                'fields' => $summary->fields($request->user(), $submission),
            ])->filter(fn (array $form) => $form['fields'] !== []),
        ]);

        return view('medical-records.encounters', compact('patient', 'encounters', 'canCreate', 'manageableEncounters', 'formSummaries'));
    }

    public function edit(Request $request, MedicalEncounter $encounter, MedicalRecordAccess $access, ClinicalHtml $html): View
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        $note = $encounter->note_html !== null
            ? $html->clean($encounter->note_html)
            : nl2br(e($encounter->note ?? ''), false);
        if (is_string($draft = $request->old('note'))) {
            $note = $html->clean($draft);
        }

        return view('medical-records.edit', ['patient' => $encounter->patient, 'encounter' => $encounter, 'note' => $note]);
    }

    public function update(Request $request, MedicalEncounter $encounter, MedicalRecordAccess $access, ManageMedicalRecord $records): RedirectResponse
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        $data = $request->validate(['note' => ['present', 'nullable', 'string', 'max:100000']]);
        $records->updateNote($request->user(), $encounter, $data['note']);

        return redirect()->route('medical-records.encounters', $encounter->patient)
            ->withFragment('encounter-'.$encounter->public_id)
            ->with('status', __('medical_records.encounter_updated'));
    }

    public function destroy(Request $request, MedicalEncounter $encounter, MedicalRecordAccess $access, ManageMedicalRecord $records): RedirectResponse
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        $records->delete($request->user(), $encounter);

        return redirect()->route('medical-records.encounters', $encounter->patient)->with('status', __('medical_records.encounter_deleted'));
    }

    public function store(Request $request, Patient $patient, FormAccess $formAccess, FormRuntime $runtime, ManageMedicalRecord $records): RedirectResponse
    {
        Gate::authorize('medical-records.manage');
        abort_unless($request->user()->hasRole('doctor'), 403);
        $doctor = $request->user()->doctor()->where('active', true)->firstOrFail();
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:100000'],
            'values' => ['sometimes', 'array'],
        ]);
        $availableVersions = $this->publishedFormVersions($doctor)->keyBy('id');
        $uploads = $request->file('values', []);
        abort_unless(is_array($uploads), 422);
        $submittedVersionIds = array_map('strval', array_unique([...array_keys($data['values'] ?? []), ...array_keys($uploads)]));
        $availableVersionIds = $availableVersions->keys()->map(fn (int $id): string => (string) $id)->all();
        abort_unless(count(array_diff($submittedVersionIds, $availableVersionIds)) === 0, 404);
        $validatedForms = [];
        foreach ($availableVersions as $version) {
            $values = $data['values'][$version->id] ?? [];
            $formUploads = $uploads[$version->id] ?? [];
            abort_unless(is_array($values) && is_array($formUploads), 422);
            $values = array_diff_key($values, $formUploads);
            $formAccess->authorize($request->user(), $version->template);
            $schema = $formAccess->schemaFor($request->user(), $version->template, $version->schema_json);
            $validatedForms[$version->id] = [
                'values' => $runtime->validate($request->user(), $version->template, $schema, $values, $formUploads),
                'schema' => $schema,
                'uploads' => $formUploads,
            ];
        }

        DB::transaction(function () use ($request, $patient, $doctor, $data, $records, $availableVersions, $validatedForms): void {
            $encounter = $records->create($request->user(), $patient, $doctor->id, $data['note'] ?? null);

            foreach ($availableVersions as $version) {
                $form = $validatedForms[$version->id];
                $records->attachSubmission(
                    $request->user(),
                    $encounter,
                    $version,
                    $form['values'],
                    fn (): array => ['values' => $form['values'], 'schema' => $form['schema']],
                    $form['uploads'],
                );
            }
        });

        return redirect()->route('medical-records.index', $patient)->with('status', __('medical_records.created'));
    }

    /** @return Collection<int, FormVersion> */
    private function publishedFormVersions(Doctor $doctor): Collection
    {
        return FormTemplate::query()
            ->where('owner_doctor_id', $doctor->id)
            ->where('active', true)
            ->whereHas('currentVersion', fn ($query) => $query->where('status', 'published'))
            ->with('currentVersion.template')
            ->get()
            ->pluck('currentVersion')
            ->filter()
            ->values();
    }

    public function summary(Request $request, Patient $patient, MedicalRecordAccess $access, ClinicalHtml $html): JsonResponse|RedirectResponse
    {
        $access->authorizePatient($request->user(), $patient);
        abort_unless($request->user()->hasRole('doctor') && $request->user()->can('medical-records.manage'), 403);
        $doctor = $request->user()->doctor()->where('active', true)->firstOrFail();
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:100000']]);
        $body = $html->clean($data['summary'] ?? '');
        DB::table('patient_chronic_summaries')->insert([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'created_by' => $request->user()->id,
            'body_html' => $body, 'language' => $doctor->chart_language, 'created_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => $body,
                'direction' => $doctor->chart_language === 'en' ? 'ltr' : 'rtl',
                'message' => __('medical_records.summary_saved'),
            ])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', __('medical_records.summary_saved'));
    }

    public function form(Request $request, MedicalEncounter $encounter, FormVersion $version, FormAccess $formAccess, MedicalRecordAccess $access): View
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        abort_unless($version->status === 'published' && $version->template->active, 404);
        abort_unless($version->template->versions()->orderByDesc('version_number')->value('id') === $version->id, 404);
        abort_unless($version->template->owner_doctor_id === $encounter->doctor_id || $version->template->recipients()->whereKey($encounter->doctor_id)->exists(), 404);
        $formAccess->authorize($request->user(), $version->template);
        $schema = $formAccess->schemaFor($request->user(), $version->template, $version->schema_json);
        $values = [];
        $submission = null;
        $isCurrent = true;

        return view('forms.preview', compact('version', 'schema', 'values', 'submission', 'encounter', 'isCurrent'));
    }

    public function submitForm(Request $request, MedicalEncounter $encounter, FormVersion $version, FormAccess $access, FormRuntime $runtime, ManageMedicalRecord $records, MedicalRecordAccess $clinicalAccess): RedirectResponse
    {
        $clinicalAccess->authorizeEncounter($request->user(), $encounter, true);
        $values = $request->validate(['values' => ['sometimes', 'array', 'max:200']])['values'] ?? [];
        $uploads = $request->file('values', []);
        abort_unless(is_array($uploads), 422);
        $values = array_diff_key($values, $uploads);
        $records->attachSubmission($request->user(), $encounter, $version, $values, function (FormVersion $version, array $values, array $uploads) use ($request, $access, $runtime): array {
            $access->authorize($request->user(), $version->template);
            $schema = $access->schemaFor($request->user(), $version->template, $version->schema_json);

            return [
                'values' => $runtime->validate($request->user(), $version->template, $schema, $values, $uploads),
                'schema' => $schema,
            ];
        }, $uploads);

        return redirect()->route('medical-records.index', $encounter->patient)->with('status', __('medical_records.form_saved'));
    }

    public function amend(Request $request, MedicalEncounter $encounter, ManageMedicalRecord $records, MedicalRecordAccess $access): RedirectResponse
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);
        $records->amend($request->user(), $encounter, $data['body']);

        return back()->with('status', __('medical_records.amended'));
    }

    public function upload(Request $request, MedicalEncounter $encounter, ManageMedicalRecord $records, MedicalRecordAccess $access): RedirectResponse
    {
        $access->authorizeEncounter($request->user(), $encounter, true);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,txt', 'max:10240']]);
        $records->upload($request->user(), $encounter, $data['file']);

        return back()->with('status', __('medical_records.file_saved'));
    }
}
