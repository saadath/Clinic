<x-staff-page :title="__('patients.'.($patient->exists ? 'edit' : 'create'))">
    @if($duplicates->isNotEmpty())
        <div class="panel mb-5"><p class="mb-3 text-sm">{{ __('patients.existing') }}</p>
            @foreach($duplicates as $duplicate)<a class="block py-2 text-sm text-teal-700 dark:text-teal-300" href="{{ route('patients.show', $duplicate) }}">{{ $duplicate->first_name }} {{ $duplicate->last_name }}</a>@endforeach
        </div>
    @endif
    <form class="panel grid max-w-5xl gap-5 md:grid-cols-2" method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}">
        @csrf @if($patient->exists) @method('PUT') @endif
        @foreach(['first_name','last_name','national_id','mobile','birth_date','email','occupation','emergency_name','emergency_mobile','insurance_name','insurance_number','referral_source'] as $field)
            <label class="flex flex-col gap-2 text-sm"><span>{{ __('patients.'.$field) }}</span><input class="input" name="{{ $field }}" type="{{ $field === 'birth_date' ? 'date' : ($field === 'email' ? 'email' : 'text') }}" value="{{ old($field, $field === 'birth_date' ? $patient->birth_date?->format('Y-m-d') : $patient->$field) }}" @required(in_array($field, ['first_name','last_name','national_id','mobile','birth_date'])) @if(in_array($field, ['national_id','mobile','emergency_mobile'])) inputmode="numeric" dir="ltr" @endif @if($errors->has($field)) aria-invalid="true" @endif></label>
        @endforeach
        <p class="text-xs text-slate-500 md:col-span-2">{{ __('patients.date_hint') }}</p>
        @foreach(['sex' => ['female','male','other'], 'marital_status' => ['single','married','other']] as $field => $options)<label class="flex flex-col gap-2 text-sm"><span>{{ __('patients.'.$field) }}</span><select class="input" name="{{ $field }}" @required($field === 'sex')><option value="">—</option>@foreach($options as $option)<option value="{{ $option }}" @selected(old($field, $patient->$field) === $option)>{{ __('patients.'.$option) }}</option>@endforeach</select></label>@endforeach
        @foreach(['address','notes'] as $field)<label class="flex flex-col gap-2 text-sm"><span>{{ __('patients.'.$field) }}</span><textarea class="input" name="{{ $field }}" rows="3">{{ old($field, $patient->$field) }}</textarea></label>@endforeach
        <label class="flex flex-col gap-2 text-sm"><span>{{ __('patients.doctor_id') }}</span><select class="input" name="doctor_id"><option value="">—</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->user->name }}</option>@endforeach</select></label>
        <p class="text-xs leading-6 text-slate-500">{{ __('patients.link_hint') }}</p>
        <label class="flex items-center gap-3 text-sm md:col-span-2"><input name="shared_mobile_confirmed" type="checkbox" value="1" @checked(old('shared_mobile_confirmed'))>{{ __('patients.shared_mobile_confirmed') }}</label>
        <div class="flex gap-4 md:col-span-2"><button class="button">{{ __('patients.save') }}</button><a class="px-4 py-3 text-sm" href="{{ route('patients.index') }}">{{ __('patients.back') }}</a></div>
    </form>
</x-staff-page>
