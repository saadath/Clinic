<x-staff-page :title="$version->template->name">
    @if($encounter ?? null)
        <p class="mb-5 text-sm">{{ __('medical_records.title') }} — {{ $encounter->patient->first_name }} {{ $encounter->patient->last_name }}</p>
        <a class="mb-5 inline-block text-sm" href="{{ route('medical-records.index', $encounter->patient) }}">{{ __('patients.back') }}</a>
    @else
        <p class="mb-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ __('forms.sample_notice') }}</p>
    @endif
    @if($submission)<p class="mb-5 text-sm">{{ ($encounter ?? null) ? __('medical_records.form_saved') : __('forms.sample_saved') }}</p>@endif
    <form data-form-runtime x-data="formRuntime" data-schema="{{ json_encode($schema, JSON_THROW_ON_ERROR) }}" data-values="{{ json_encode(old('values', $values), JSON_THROW_ON_ERROR) }}" method="POST" enctype="multipart/form-data" action="{{ ($encounter ?? null) ? route('medical-records.forms.store', [$encounter, $version]) : route('form-submissions.store', $version) }}" class="grid gap-5">
        @csrf
        @include('forms.runtime-fields', ['schema' => $schema, 'version' => $version, 'submission' => $submission, 'valuesPrefix' => 'values'])
        @if(!$submission && $isCurrent && $version->status === 'published' && $version->template->active)
            @if($encounter ?? null)
                @can('medical-records.manage')<button class="button justify-self-start">{{ __('medical_records.save_form') }}</button>@endcan
            @else
                @can('forms.test')<button class="button justify-self-start">{{ __('forms.sample_save') }}</button>@endcan
            @endif
        @endif
    </form>
</x-staff-page>
