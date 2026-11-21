<x-staff-page :title="__('staff.roles').' — '.__('staff.'.($role->exists ? 'edit' : 'create'))">
    <form class="panel grid max-w-3xl gap-5" method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf @if($role->exists) @method('PUT') @endif
        @if($role->exists && $role->system)
            <div class="grid gap-1 text-sm">
                <span class="font-semibold">{{ __('staff.role_'.$role->name) }}</span>
                <span class="text-xs text-slate-500" dir="ltr">{{ $role->name }}</span>
                <span class="text-xs text-slate-500">{{ __('staff.system_locked') }}</span>
            </div>
            <input type="hidden" name="name" value="{{ $role->name }}">
            <input type="hidden" name="label" value="{{ $role->label }}">
        @else
            <x-staff-field name="name" :value="$role->name" required dir="ltr"/>
            <x-staff-field name="label" :value="$role->label" required/>
        @endif
        <fieldset>
            <legend class="mb-4 font-semibold">{{ __('staff.permissions') }}</legend>
            @if($role->exists && $role->name === 'super-admin')
                <p class="mb-3 text-xs text-slate-500">{{ __('staff.super_admin_permissions_hint') }}</p>
                <div class="flex flex-col gap-3">@foreach($permissions as $permission)<label class="flex items-center gap-2 text-sm"><input type="checkbox" checked disabled>{{ __('staff.permission_'.$permission) }}</label>@endforeach</div>
            @else
                <div class="flex flex-col gap-3">@foreach($permissions as $permission)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : [])))>{{ __('staff.permission_'.$permission) }}</label>@endforeach</div>
            @endif
        </fieldset>
        <div class="flex gap-4"><button class="button">{{ __('staff.save') }}</button><a class="px-4 py-3 text-sm" href="{{ route('roles.index') }}">{{ __('staff.cancel') }}</a></div>
    </form>
</x-staff-page>
