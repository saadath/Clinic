<x-staff-page :title="$doctor->user->name">
    <dl class="panel grid max-w-3xl gap-5 sm:grid-cols-2">@foreach(['medical_council_number','specialty','subspecialty','visit_duration_minutes','visit_price_rial','bio'] as $field)<div @class(['sm:col-span-2' => $field === 'bio'])><dt class="mb-2 text-xs text-slate-500">{{ __('staff.'.$field) }}</dt><dd class="whitespace-pre-wrap text-sm">{{ $doctor->$field ?? '—' }}</dd></div>@endforeach</dl>
    <a class="mt-5 inline-block text-sm text-teal-600 dark:text-teal-300" href="{{ route('doctors.index') }}">{{ __('staff.cancel') }}</a>
</x-staff-page>
