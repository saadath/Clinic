<?php

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
    Route::get('forms', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'index'])->name('forms.index');
    Route::get('forms/create', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'create'])->name('forms.create');
    Route::post('forms', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'store'])->name('forms.store');
    Route::post('forms/{template}/draft', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'edit'])->name('forms.edit');
    Route::put('forms/{template}', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'update'])->name('forms.update');
    Route::post('forms/{template}/copy', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'copy'])->name('forms.copy');
    Route::post('forms/{template}/share', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'share'])->name('forms.share');
    Route::post('forms/{template}/default', [\App\Http\Controllers\Modules\Forms\FormTemplateController::class, 'default'])->name('forms.default');
    Route::get('form-versions/{version}/edit', [\App\Http\Controllers\Modules\Forms\FormVersionController::class, 'edit'])->name('form-versions.edit');
    Route::put('form-versions/{version}', [\App\Http\Controllers\Modules\Forms\FormVersionController::class, 'update'])->name('form-versions.update');
    Route::post('form-versions/{version}/publish', [\App\Http\Controllers\Modules\Forms\FormVersionController::class, 'publish'])->name('form-versions.publish');
    Route::get('form-versions/{version}', [\App\Http\Controllers\Modules\Forms\FormVersionController::class, 'show'])->name('form-versions.show');
    Route::post('form-versions/{version}/submissions', [\App\Http\Controllers\Modules\Forms\FormSubmissionController::class, 'store'])->name('form-submissions.store');
    Route::get('form-submissions/{submission}', [\App\Http\Controllers\Modules\Forms\FormSubmissionController::class, 'show'])->name('form-submissions.show');
    Route::resource('patients', PatientController::class)->except('destroy');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    Route::resource('doctors', DoctorController::class)->except('destroy');
    Route::resource('assignments', DoctorStaffAssignmentController::class)->only(['index', 'store', 'destroy']);
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
