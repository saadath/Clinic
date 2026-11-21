@php
    $user = auth()->user();
    $canPatients = $user->can('patients.view');
    $canForms = $user->can('forms.view');
    $isPersian = app()->getLocale() === 'fa';
    $number = fn (int|float $value): string => $isPersian
        ? str_replace(',', '٬', \App\Services\Patients\PatientInput::persianDigits(number_format($value)))
        : number_format($value);
    $digits = fn (?string $value): string => $isPersian
        ? \App\Services\Patients\PatientInput::persianDigits($value)
        : (string) $value;
    $hour = (int) now('Asia/Tehran')->format('G');
    $greeting = match (true) {
        $hour < 12 => __('dashboard.morning'),
        $hour < 17 => __('dashboard.afternoon'),
        $hour < 20 => __('dashboard.evening'),
        default => __('dashboard.night'),
    };
    $doctorName = $doctor?->user?->name ?? $user->name;
    $initials = collect(explode(' ', trim($doctorName)))->filter()->take(2)->map(fn (string $part): string => mb_substr($part, 0, 1))->implode('');
@endphp

<x-layout :title="__('ui.dashboard')">
    <div class="grid gap-6">
        <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-teal-800 via-teal-900 to-slate-900 p-6 text-white shadow-xl shadow-teal-900/10 md:p-9">
            <div class="pointer-events-none absolute -end-24 -top-28 size-80 rounded-full border-[45px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-32 start-1/4 size-80 rounded-full bg-teal-400/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-8">
                <div class="min-w-0 max-w-2xl">
                    <p class="text-sm text-teal-100/80">{{ $greeting }}، {{ $user->name }}</p>
                    <h1 class="mt-2 text-2xl font-bold md:text-3xl">{{ $roleTitle ? __('dashboard.role_title', ['role' => $roleTitle]) : __('ui.dashboard') }}</h1>
                    <p class="mt-3 text-sm leading-7 text-teal-100/70">{{ __('dashboard.intro_'.($doctor ? 'doctor' : 'staff')) }}</p>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs">
                        @if($doctor?->specialty)
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-teal-50"><x-icon name="shield" class="size-3.5"/>{{ $doctor->specialty }}</span>
                        @endif
                        @if($doctor?->medical_council_number)
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-teal-50">{{ __('dashboard.council_number') }} <bdi class="font-semibold tabular-nums">{{ $digits($doctor->medical_council_number) }}</bdi></span>
                        @endif
                    </div>
                    <div class="mt-7 flex flex-wrap gap-3">
                        @can('patients.create')
                            <a href="{{ route('patients.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><x-icon name="user-plus" class="size-4"/>{{ __('dashboard.new_patient') }}</a>
                        @endcan
                    </div>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur">
                    <p class="mb-1.5 text-[11px] font-medium tracking-wide text-teal-100/60">{{ __('ui.today') }}</p>
                    <x-today-date class="block text-sm font-semibold text-white"/>
                </div>
            </div>
        </section>

        @if($canPatients || $canForms)
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @can('patients.view')
                    <a href="{{ route('patients.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-sky-700">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('dashboard.patients') }}</p>
                                <p class="mt-2 text-3xl font-bold tabular-nums">{{ $number($patientCount ?? 0) }}</p>
                            </div>
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300"><x-icon name="users"/></span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('dashboard.patients_hint') }}</p>
                    </a>
                @endcan
                @can('forms.view')
                    <a href="{{ route('forms.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-violet-700">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('dashboard.active_forms') }}</p>
                                <p class="mt-2 text-3xl font-bold tabular-nums">{{ $number($formCount ?? 0) }}</p>
                            </div>
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300"><x-icon name="file"/></span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('dashboard.active_forms_hint') }}</p>
                    </a>
                @endcan
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">{{ __('dashboard.quick_actions') }}</h2>
            <div class="mt-4 grid gap-2">
                @can('patients.view')
                    <a href="{{ route('patients.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-100 p-3 text-sm font-medium transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-slate-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/40">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300"><x-icon name="users" class="size-4"/></span>
                        <span class="flex-1">{{ __('staff.patients') }}</span>
                        <x-icon name="arrow" class="size-3.5 text-slate-400 transition group-hover:text-teal-600"/>
                    </a>
                @endcan
                @can('forms.view')
                    <a href="{{ route('forms.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-100 p-3 text-sm font-medium transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-slate-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/40">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300"><x-icon name="file" class="size-4"/></span>
                        <span class="flex-1">{{ __('staff.forms') }}</span>
                        <x-icon name="arrow" class="size-3.5 text-slate-400 transition group-hover:text-teal-600"/>
                    </a>
                @endcan
                @if($doctor)
                    <a href="{{ route('doctor-settings.edit') }}" class="group flex items-center gap-3 rounded-xl border border-slate-100 p-3 text-sm font-medium transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-slate-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/40">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="settings" class="size-4"/></span>
                        <span class="flex-1">{{ __('medical_records.doctor_settings') }}</span>
                        <x-icon name="arrow" class="size-3.5 text-slate-400 transition group-hover:text-teal-600"/>
                    </a>
                @endif
                <a href="{{ route('settings') }}" class="group flex items-center gap-3 rounded-xl border border-slate-100 p-3 text-sm font-medium transition hover:border-teal-200 hover:bg-teal-50/50 dark:border-slate-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/40">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="settings" class="size-4"/></span>
                    <span class="flex-1">{{ __('ui.settings') }}</span>
                    <x-icon name="arrow" class="size-3.5 text-slate-400 transition group-hover:text-teal-600"/>
                </a>
            </div>
        </section>

        @if($doctor)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold">{{ __('dashboard.doctor_profile') }}</h2>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><x-icon name="shield" class="size-4"/></span>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-teal-600 to-teal-800 text-sm font-bold text-white">{{ $initials }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $doctorName }}</p>
                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $doctor->specialty ?: __('dashboard.specialty_unset') }}</p>
                    </div>
                </div>
                <dl class="mt-4 grid gap-2.5 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('dashboard.council_number') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ $doctor->medical_council_number ? $digits($doctor->medical_council_number) : '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('dashboard.visit_duration') }}</dt>
                        <dd class="font-semibold">{{ $doctor->visit_duration_minutes ? __('dashboard.minutes', ['count' => $number($doctor->visit_duration_minutes)]) : __('dashboard.not_set') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('dashboard.visit_fee') }}</dt>
                        <dd class="font-semibold">{{ $doctor->visit_price_rial ? __('dashboard.rial', ['amount' => $number($doctor->visit_price_rial)]) : __('dashboard.not_set') }}</dd>
                    </div>
                </dl>
                <a href="{{ route('doctor-settings.edit') }}" class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300"><x-icon name="pencil" class="size-3.5"/>{{ __('dashboard.edit_profile') }}</a>
            </section>
        @endif
    </div>
</x-layout>