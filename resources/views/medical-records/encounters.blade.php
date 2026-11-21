<x-staff-page :title="__('medical_records.encounters_title').' — '.$patient->first_name.' '.$patient->last_name">
    <div class="grid gap-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('medical-records.index', $patient) }}" class="text-sm text-teal-700 dark:text-teal-300">{{ __('patients.back') }}</a>
            @if($canCreate)
                <a href="{{ route('medical-records.create', $patient) }}" class="button gap-2">
                    <x-icon name="file" class="size-4"/>
                    {{ __('medical_records.create') }}
                </a>
            @endif
        </div>

        @forelse($encounters as $encounter)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900" id="encounter-{{ $encounter->public_id }}">
                <header class="flex items-center justify-between gap-3 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-white px-5 py-3 dark:border-teal-950 dark:from-teal-950/50 dark:to-slate-900">
                    <h2 class="text-sm font-bold"><bdi>{{ \App\Services\PersianDate::formatPersianDigits($encounter->created_at) }}</bdi></h2>
                    @if(in_array($encounter->id, $manageableEncounters, true))
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('medical-records.edit', $encounter) }}" class="grid size-11 place-items-center rounded-xl bg-teal-100 text-teal-800 transition-colors hover:bg-teal-200 focus-visible:outline-2 focus-visible:outline-offset-2 dark:bg-teal-950 dark:text-teal-200 dark:hover:bg-teal-900" title="{{ __('medical_records.edit_encounter') }}" aria-label="{{ __('medical_records.edit_encounter') }}">
                                <x-icon name="pencil"/>
                            </a>
                            <form x-data method="POST" action="{{ route('medical-records.destroy', $encounter) }}" @submit="if (!window.confirm($el.dataset.confirm)) $event.preventDefault()" data-confirm="{{ __('medical_records.delete_encounter_confirm') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="grid size-11 cursor-pointer place-items-center rounded-xl bg-red-50 text-red-700 transition-colors hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-offset-2 dark:bg-red-950 dark:text-red-200 dark:hover:bg-red-900" title="{{ __('medical_records.delete_encounter') }}" aria-label="{{ __('medical_records.delete_encounter') }}">
                                    <x-icon name="trash"/>
                                </button>
                            </form>
                        </div>
                    @endif
                </header>
                <div class="p-5">
                    @if($encounter->note_html !== null)
                        <div class="clinical-content text-sm" dir="{{ $encounter->note_language === 'en' ? 'ltr' : 'rtl' }}">{!! app(\App\Services\MedicalRecords\ClinicalHtml::class)->clean($encounter->note_html) !!}</div>
                    @else
                        <p class="whitespace-pre-wrap break-words text-sm">{{ $encounter->note }}</p>
                    @endif
                </div>
                @if($formSummaries[$encounter->id]->isNotEmpty())
                    <section class="grid gap-5 divide-y divide-slate-200 border-t border-slate-200 p-5 dark:divide-slate-700 dark:border-slate-700">
                        @foreach($formSummaries[$encounter->id] as $form)
                            @include('medical-records.form-summary', ['form' => $form])
                        @endforeach
                    </section>
                @endif
            </article>
        @empty
            <div class="panel grid justify-items-center gap-3 py-12 text-center">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><x-icon name="calendar" class="size-7"/></span>
                <p class="text-sm text-slate-500">{{ __('medical_records.no_encounters') }}</p>
            </div>
        @endforelse
    </div>
</x-staff-page>
