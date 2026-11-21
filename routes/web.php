<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Modules\Forms\FormSubmissionController;
use App\Http\Controllers\Modules\Forms\FormSubmissionFileController;
use App\Http\Controllers\Modules\Forms\FormTemplateController;
use App\Http\Controllers\Modules\Forms\FormVersionController;
use App\Http\Controllers\Modules\Identity\DoctorController;
use App\Http\Controllers\Modules\Identity\DoctorSettingController;
use App\Http\Controllers\Modules\Identity\DoctorStaffAssignmentController;
use App\Http\Controllers\Modules\Identity\PreferenceController;
use App\Http\Controllers\Modules\Identity\RoleController;
use App\Http\Controllers\Modules\Identity\SessionController;
use App\Http\Controllers\Modules\Identity\UserController;
use App\Http\Controllers\Modules\MedicalRecords\MedicalRecordController;
use App\Http\Controllers\Modules\MedicalRecords\MedicalRecordFileController;
use App\Http\Controllers\Modules\Patients\PatientController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});

Route::patch('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:60,1')->name('preferences.update');

Route::middleware('auth')->group(function (): void {
    Route::get('doctor-settings', [DoctorSettingController::class, 'edit'])->name('doctor-settings.edit');
    Route::put('doctor-settings', [DoctorSettingController::class, 'update'])->name('doctor-settings.update');
    Route::post('patients/{patient}/chronic-summary', [MedicalRecordController::class, 'summary'])->name('medical-records.summary');
    Route::get('forms', [FormTemplateController::class, 'index'])->name('forms.index');
    Route::get('forms/create', [FormTemplateController::class, 'create'])->name('forms.create');
    Route::post('forms', [FormTemplateController::class, 'store'])->name('forms.store');
    Route::get('forms/{template}/edit', [FormTemplateController::class, 'edit'])->name('forms.edit');
    Route::put('forms/{template}', [FormTemplateController::class, 'update'])->name('forms.update');
    Route::delete('forms/{template}', [FormTemplateController::class, 'destroy'])->name('forms.destroy');
    Route::post('forms/{template}/copy', [FormTemplateController::class, 'copy'])->name('forms.copy');
    Route::post('forms/{template}/share', [FormTemplateController::class, 'share'])->name('forms.share');
    Route::post('forms/{template}/default', [FormTemplateController::class, 'default'])->name('forms.default');
    Route::get('form-versions/{version}/edit', [FormVersionController::class, 'edit'])->name('form-versions.edit');
    Route::put('form-versions/{version}', [FormVersionController::class, 'update'])->name('form-versions.update');
    Route::get('form-versions/{version}', [FormVersionController::class, 'show'])->name('form-versions.show');
    Route::post('form-versions/{version}/submissions', [FormSubmissionController::class, 'store'])->name('form-submissions.store');
    Route::get('form-submissions/{submission}', [FormSubmissionController::class, 'show'])->name('form-submissions.show');
    Route::get('form-submission-files/{file}', [FormSubmissionFileController::class, 'show'])->name('form-submission-files.show');
    Route::get('patients/{patient}/medical-record', [MedicalRecordController::class, 'index'])->name('medical-records.index');
    Route::get('patients/{patient}/medical-record/report', [MedicalRecordController::class, 'report'])->name('medical-records.report');
    Route::get('patients/{patient}/medical-record/encounters/create', [MedicalRecordController::class, 'create'])->name('medical-records.create');
    Route::get('patients/{patient}/medical-record/encounters', [MedicalRecordController::class, 'encounters'])->name('medical-records.encounters');
    Route::post('patients/{patient}/medical-record/encounters', [MedicalRecordController::class, 'store'])->name('medical-records.store');
    Route::get('medical-record/encounters/{encounter}/edit', [MedicalRecordController::class, 'edit'])->name('medical-records.edit');
    Route::patch('medical-record/encounters/{encounter}', [MedicalRecordController::class, 'update'])->name('medical-records.update');
    Route::delete('medical-record/encounters/{encounter}', [MedicalRecordController::class, 'destroy'])->name('medical-records.destroy');
    Route::get('medical-record/encounters/{encounter}/forms/{version}', [MedicalRecordController::class, 'form'])->name('medical-records.forms.show');
    Route::post('medical-record/encounters/{encounter}/forms/{version}', [MedicalRecordController::class, 'submitForm'])->name('medical-records.forms.store');
    Route::post('medical-record/encounters/{encounter}/amendments', [MedicalRecordController::class, 'amend'])->name('medical-records.amendments.store');
    Route::post('medical-record/encounters/{encounter}/files', [MedicalRecordController::class, 'upload'])->name('medical-records.files.store');
    Route::get('medical-record/files/{file}', [MedicalRecordFileController::class, 'show'])->name('medical-records.files.show');
    Route::resource('patients', PatientController::class)->except('destroy');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    Route::resource('doctors', DoctorController::class)->except('destroy');
    Route::resource('assignments', DoctorStaffAssignmentController::class)->only(['index', 'store', 'destroy']);
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
