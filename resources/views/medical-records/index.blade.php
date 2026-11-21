<x-staff-page :title="__('medical_records.title').' — '.$patient->first_name.' '.$patient->last_name">
    <div class="grid gap-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('patients.index') }}" class="text-sm text-teal-700 dark:text-teal-300">{{ __('patients.back') }}</a>
            @if($chartDoctor && auth()->user()->can('medical-records.manage'))
                <a href="{{ route('medical-records.create', $patient) }}" class="button gap-2">
                    <x-icon name="file" class="size-4"/>
                    {{ __('medical_records.create') }}
                </a>
            @endif
            <a href="{{ route('medical-records.encounters', $patient) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-teal-700 px-5 py-3 text-sm font-semibold text-teal-700 transition hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-teal-400 dark:text-teal-300 dark:hover:bg-teal-950">
                <x-icon name="calendar" class="size-4"/>
                {{ __('medical_records.view_encounters') }}
            </a>
            @foreach(['html' => 'file', 'pdf' => 'printer'] as $format => $icon)
                <a href="{{ route('medical-records.report', ['patient' => $patient, 'format' => $format]) }}" target="_blank" rel="noopener noreferrer" title="{{ __('medical_records.report_'.$format) }}" aria-label="{{ __('medical_records.report_'.$format) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:border-teal-600 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-teal-950">
                    <x-icon :name="$icon" class="size-4"/><bdi>{{ strtoupper($format) }}</bdi>
                </a>
            @endforeach
        </div>
        <section class="rounded-2xl border border-teal-100 bg-white p-5 shadow-sm dark:border-teal-900 dark:bg-slate-900" aria-label="{{ __('patients.details') }}">
            <dl class="grid grid-cols-2 gap-4 text-xs md:grid-cols-4">
                <div><dt class="mb-2 text-slate-500">{{ __('patients.first_name') }} / {{ __('patients.last_name') }}</dt><dd class="font-semibold">{{ $patient->first_name }} {{ $patient->last_name }}</dd></div>
                <div><dt class="mb-2 text-slate-500">{{ __('medical_records.age') }}</dt><dd>{{ $patient->birth_date ? (int) $patient->birth_date->age : '—' }}</dd></div>
                <div><dt class="mb-2 text-slate-500">{{ __('patients.mobile') }}</dt><dd><bdi>{{ \App\Services\Patients\PatientInput::persianDigits($patient->mobile) }}</bdi></dd></div>
                <div><dt class="mb-2 text-slate-500">{{ __('patients.national_id') }}</dt><dd><bdi>{{ \App\Services\Patients\PatientInput::persianDigits($patient->national_id) }}</bdi></dd></div>
            </dl>
        </section>
        <section class="panel grid gap-4" x-data="chronicSummary" data-has-summary="{{ $summary ? '1' : '0' }}" data-summary-error="{{ $errors->first('summary') }}" data-save-error="{{ __('medical_records.summary_save_failed') }}">
            <h2 class="text-sm font-semibold">{{ __('medical_records.chronic_summary') }}</h2>
            <p class="text-sm text-teal-700 dark:text-teal-300" role="status" x-show="status" x-cloak x-text="status"></p>
            <div x-show="! editing" class="grid gap-4">
                <div x-ref="summary" x-show="hasSummary" @if(!$summary) x-cloak @endif class="clinical-content" style="font-size: {{ $chronicSummaryFontSize }}px" dir="{{ $summary?->language === 'en' ? 'ltr' : 'rtl' }}">{!! $summary ? app(\App\Services\MedicalRecords\ClinicalHtml::class)->clean($summary->body_html) : '' !!}</div>
                <p x-show="!hasSummary" @if($summary) x-cloak @endif class="text-xs text-slate-500">{{ __('medical_records.no_summary') }}</p>
                @if($chartDoctor && auth()->user()->can('medical-records.manage'))
                    <button type="button" @click="editing = true" class="inline-flex items-center gap-2 justify-self-start rounded-xl px-3 py-2 text-xs font-semibold text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-teal-950">
                        <x-icon name="file" class="size-4"/>
                        {{ __('medical_records.edit_summary') }}
                    </button>
                @endif
            </div>
            @if($chartDoctor && auth()->user()->can('medical-records.manage'))
                <form x-cloak x-show="editing" @submit.prevent="save($event.currentTarget)" :aria-busy="saving" class="grid gap-4" method="POST" action="{{ route('medical-records.summary', $patient) }}">@csrf
                    <fieldset :inert="saving" class="grid min-w-0 gap-4 border-0 p-0">
                        <x-clinical-editor name="summary" :value="old('summary', $summary->body_html ?? '')" :language="$chartLanguage" :font-size="$chronicSummaryFontSize"/>
                        <div class="flex flex-wrap items-center gap-3">
                            <button class="button justify-self-start" type="submit" :disabled="saving">{{ __('patients.save') }}</button>
                            <button class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" type="button" @click="editing = false">{{ __('staff.cancel') }}</button>
                        </div>
                    </fieldset>
                    <p class="text-sm text-red-700 dark:text-red-300" role="alert" x-show="error" x-text="error"></p>
                </form>
            @endif
        </section>
    </div>
</x-staff-page>
