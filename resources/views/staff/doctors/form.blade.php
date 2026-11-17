<x-staff-page :title="__('staff.doctors').' — '.__('staff.'.($doctor->exists ? 'edit' : 'create'))">
    <form class="panel grid max-w-4xl gap-5 md:grid-cols-2" method="POST" action="{{ $doctor->exists ? route('doctors.update', $doctor) : route('doctors.store') }}">
        @csrf @if($doctor->exists) @method('PUT') @endif
        <label class="flex flex-col gap-2 text-sm"><span>{{ __('staff.account') }}</span><select class="input" name="user_id" required><option value="">—</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('user_id', $doctor->user_id) == $account->id)>{{ $account->name }}</option>@endforeach</select></label>
        <x-staff-field name="medical_council_number" :value="$doctor->medical_council_number" required/>
        <x-staff-field name="specialty" :value="$doctor->specialty" required/>
        <x-staff-field name="subspecialty" :value="$doctor->subspecialty"/>
        <x-staff-field name="visit_duration_minutes" type="number" :value="$doctor->visit_duration_minutes ?? 20" min="5" max="240" required/>
        <x-staff-field name="visit_price_rial" type="number" :value="$doctor->visit_price_rial ?? 0" min="0" max="100000000000" required/>
        <label class="flex flex-col gap-2 text-sm"><span>{{ __('staff.status') }}</span><select name="active" class="input"><option value="1" @selected(old('active', $doctor->active ?? true))>{{ __('staff.active') }}</option><option value="0" @selected(! old('active', $doctor->active ?? true))>{{ __('staff.inactive') }}</option></select></label>
        <label class="flex flex-col gap-2 text-sm md:col-span-2"><span>{{ __('staff.bio') }}</span><textarea name="bio" class="input" rows="5" maxlength="10000">{{ old('bio', $doctor->bio) }}</textarea></label>
        <div class="flex gap-4 md:col-span-2"><button class="button">{{ __('staff.save') }}</button><a class="px-4 py-3 text-sm" href="{{ route('doctors.index') }}">{{ __('staff.cancel') }}</a></div>
    </form>
</x-staff-page>
