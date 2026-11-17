<x-staff-page :title="__('staff.users').' — '.__('staff.'.($account->exists ? 'edit' : 'create'))">
    <form method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="panel grid max-w-4xl gap-5 md:grid-cols-2">
        @csrf @if($account->exists) @method('PUT') @endif
        <x-staff-field name="name" :value="$account->name" required maxlength="255"/>
        <x-staff-field name="email" type="email" :value="$account->email" required dir="ltr"/>
        <x-staff-field name="mobile" :value="$account->mobile" dir="ltr"/>
        <label class="flex flex-col gap-2 text-sm"><span>{{ __('staff.status') }}</span><select name="active" class="input"><option value="1" @selected(old('active', $account->active ?? true))>{{ __('staff.active') }}</option><option value="0" @selected(! old('active', $account->active ?? true))>{{ __('staff.inactive') }}</option></select></label>
        <x-staff-field name="password" type="password" autocomplete="new-password" minlength="12" :required="! $account->exists"/>
        <x-staff-field name="password_confirmation" type="password" autocomplete="new-password" :required="! $account->exists"/>
        <p class="text-xs text-slate-500 md:col-span-2">{{ __('staff.password_hint') }}</p>
        <fieldset class="md:col-span-2"><legend class="mb-3 text-sm font-semibold">{{ __('staff.roles') }}</legend><div class="grid gap-3 sm:grid-cols-2">@foreach($roles as $role)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $account->roles->pluck('id')->all())))>{{ $role->system ? __('staff.role_'.$role->name) : $role->label }}</label>@endforeach</div></fieldset>
        <div class="flex gap-4 md:col-span-2"><button class="button">{{ __('staff.save') }}</button><a class="px-4 py-3 text-sm" href="{{ route('users.index') }}">{{ __('staff.cancel') }}</a></div>
    </form>
</x-staff-page>
