<?php

use App\Http\Controllers\Modules\Appointments\AppointmentController;
use App\Http\Controllers\Modules\Appointments\ScheduleController;
use App\Http\Controllers\Modules\Forms\FormSubmissionController;
use App\Http\Controllers\Modules\Forms\FormTemplateController;
use App\Http\Controllers\Modules\Forms\FormVersionController;
use App\Http\Controllers\Modules\Identity\DoctorController;
use App\Http\Controllers\Modules\Identity\DoctorStaffAssignmentController;
use App\Http\Controllers\Modules\Identity\PreferenceController;
use App\Http\Controllers\Modules\Identity\RoleController;
use App\Http\Controllers\Modules\Identity\SessionController;
use App\Http\Controllers\Modules\Identity\UserController;
use App\Http\Controllers\Modules\Patients\PatientController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});

Route::patch('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:60,1')->name('preferences.update');

Route::middleware('auth')->group(function (): void {
    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::post('appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::delete('schedules/{type}/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::get('forms', [FormTemplateController::class, 'index'])->name('forms.index');
    Route::get('forms/create', [FormTemplateController::class, 'create'])->name('forms.create');
    Route::post('forms', [FormTemplateController::class, 'store'])->name('forms.store');
    Route::post('forms/{template}/draft', [FormTemplateController::class, 'edit'])->name('forms.edit');
    Route::put('forms/{template}', [FormTemplateController::class, 'update'])->name('forms.update');
    Route::post('forms/{template}/copy', [FormTemplateController::class, 'copy'])->name('forms.copy');
    Route::post('forms/{template}/share', [FormTemplateController::class, 'share'])->name('forms.share');
    Route::post('forms/{template}/default', [FormTemplateController::class, 'default'])->name('forms.default');
    Route::get('form-versions/{version}/edit', [FormVersionController::class, 'edit'])->name('form-versions.edit');
    Route::put('form-versions/{version}', [FormVersionController::class, 'update'])->name('form-versions.update');
    Route::post('form-versions/{version}/publish', [FormVersionController::class, 'publish'])->name('form-versions.publish');
    Route::get('form-versions/{version}', [FormVersionController::class, 'show'])->name('form-versions.show');
    Route::post('form-versions/{version}/submissions', [FormSubmissionController::class, 'store'])->name('form-submissions.store');
    Route::get('form-submissions/{submission}', [FormSubmissionController::class, 'show'])->name('form-submissions.show');
    Route::resource('patients', PatientController::class)->except('destroy');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    Route::resource('doctors', DoctorController::class)->except('destroy');
    Route::resource('assignments', DoctorStaffAssignmentController::class)->only(['index', 'store', 'destroy']);
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
