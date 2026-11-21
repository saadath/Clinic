<div class="grid gap-4">
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 dark:bg-slate-800"><tr>@foreach(['first_name','last_name','national_id','mobile','details'] as $field)<th class="p-4 text-start">{{ __('patients.'.$field) }}</th>@endforeach<th class="p-4 text-start">{{ __('patients.edit') }}</th></tr></thead>
            <tbody>
                @forelse($patients as $patient)
                    <tr class="border-t border-slate-100 dark:border-slate-800">
                        <td class="p-4">{{ $patient->first_name }}</td><td class="p-4">{{ $patient->last_name }}</td>
                        <td class="p-4" dir="rtl"><bdi>{{ \App\Services\Patients\PatientInput::persianDigits($patient->national_id) }}</bdi></td>
                        <td class="p-4" dir="rtl"><bdi>{{ \App\Services\Patients\PatientInput::persianDigits($patient->mobile) }}</bdi></td>
                        <td class="p-4"><a class="text-teal-700 dark:text-teal-300" href="{{ route('patients.show', $patient) }}">{{ __('patients.details') }}</a></td>
                        <td class="p-4">
                            @can('patients.update')
                                <a href="{{ route('patients.edit', $patient) }}" aria-label="{{ __('patients.edit') }}: {{ $patient->first_name }} {{ $patient->last_name }}" title="{{ __('patients.edit') }}" class="inline-flex size-10 items-center justify-center rounded-xl text-teal-700 transition hover:bg-teal-50 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:text-teal-300 dark:hover:bg-teal-950 dark:hover:text-teal-100">
                                    <x-icon name="pencil" class="size-4"/>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">{{ __('patients.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $patients->links() }}</div>
</div>
