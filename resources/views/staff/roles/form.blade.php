<x-staff-page :title="__('staff.roles').' — '.__('staff.'.($role->exists ? 'edit' : 'create'))">
    <form class="panel grid max-w-3xl gap-5" method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf @if($role->exists) @method('PUT') @endif
        <x-staff-field name="name" :value="$role->name" required dir="ltr"/>
        <x-staff-field name="label" :value="$role->label" required/>
        <fieldset><legend class="mb-4 font-semibold">{{ __('staff.permissions') }}</legend><div class="flex flex-col gap-3">@foreach($permissions as $permission)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : [])))>{{ __('staff.permission_'.$permission) }}</label>@endforeach</div></fieldset>
        <div class="flex gap-4"><button class="button">{{ __('staff.save') }}</button><a class="px-4 py-3 text-sm" href="{{ route('roles.index') }}">{{ __('staff.cancel') }}</a></div>
    </form>
</x-staff-page>
