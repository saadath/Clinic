<x-staff-page :title="__('medical_records.create').' — '.$patient->first_name.' '.$patient->last_name">
    <div class="grid gap-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('medical-records.index', $patient) }}" class="text-sm text-teal-700 dark:text-teal-300">{{ __('patients.back') }}</a>
            <span class="text-sm text-slate-500">{{ $patient->first_name }} {{ $patient->last_name }}</span>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('medical-records.store', $patient) }}" class="panel grid gap-5">
            @csrf
            <h2 class="font-semibold">{{ __('medical_records.note') }}</h2>
            <x-clinical-editor name="note" :value="old('note', '')" :language="$chartLanguage"/>
            @if($forms->isNotEmpty())
                <section class="grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                    @foreach($forms as ['version' => $formVersion, 'schema' => $formSchema])
                        <section class="grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h3 class="font-semibold">{{ $formVersion->template->name }}</h3>
                            </div>
                            <div data-form-runtime x-data="formRuntime" class="grid gap-4" data-schema="{{ json_encode($formSchema, JSON_THROW_ON_ERROR) }}" data-values="{{ json_encode(old('values.'.$formVersion->id, []), JSON_THROW_ON_ERROR) }}">
                                <fieldset class="grid gap-4 border-0 p-0">
                                    @include('forms.runtime-fields', ['schema' => $formSchema, 'version' => $formVersion, 'submission' => null, 'valuesPrefix' => 'values['.$formVersion->id.']'])
                                </fieldset>
                            </div>
                        </section>
                    @endforeach
                </section>
            @endif
            <div class="flex flex-wrap items-center gap-3">
                <button class="button gap-2" type="submit"><x-icon name="save" class="size-4"/>{{ __('medical_records.save_encounter') }}</button>
                <a class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('medical-records.index', $patient) }}">{{ __('staff.cancel') }}</a>
            </div>
        </form>
    </div>
</x-staff-page>
