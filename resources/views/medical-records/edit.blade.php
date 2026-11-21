<x-staff-page :title="__('medical_records.edit_encounter').' — '.$patient->first_name.' '.$patient->last_name">
    <form method="POST" action="{{ route('medical-records.update', $encounter) }}" class="panel grid gap-5">
        @csrf
        @method('PATCH')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">{{ __('medical_records.note') }}</h2>
            <bdi class="text-sm text-slate-500">{{ \App\Services\PersianDate::formatPersianDigits($encounter->created_at) }}</bdi>
        </div>
        <x-clinical-editor name="note" :value="$note" :language="$encounter->note_language ?? 'fa'"/>
        <div class="flex flex-wrap items-center gap-3">
            <button class="button gap-2" type="submit"><x-icon name="save" class="size-4"/>{{ __('medical_records.save_encounter') }}</button>
            <a class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" href="{{ route('medical-records.encounters', $patient) }}#encounter-{{ $encounter->public_id }}">{{ __('staff.cancel') }}</a>
        </div>
    </form>
</x-staff-page>
