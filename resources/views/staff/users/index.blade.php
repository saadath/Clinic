<x-staff-page :title="__('staff.users')" create-route="users.create">
    <form class="mb-6 grid gap-3 md:grid-cols-4" method="GET">
        <input class="input" name="q" value="{{ request('q') }}" aria-label="{{ __('staff.search') }}" placeholder="{{ __('staff.search') }}">
        <select class="input" name="role" aria-label="{{ __('staff.roles') }}"><option value="">{{ __('staff.all') }} — {{ __('staff.roles') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(request('role') == $role->id)>{{ $role->system ? __('staff.role_'.$role->name) : $role->label }}</option>@endforeach</select>
        <select class="input" name="active" aria-label="{{ __('staff.status') }}"><option value="">{{ __('staff.all') }}</option><option value="1" @selected(request('active') === '1')>{{ __('staff.active') }}</option><option value="0" @selected(request('active') === '0')>{{ __('staff.inactive') }}</option></select>
        <button class="button">{{ __('staff.search') }}</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"><table class="w-full text-start text-sm">
        <thead class="bg-slate-100 text-start dark:bg-slate-800"><tr>@foreach(['name','email','mobile','roles','status','edit'] as $key)<th class="p-4 text-start">{{ __('staff.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>@forelse($users as $account)<tr class="border-t border-slate-100 dark:border-slate-800"><td class="p-4">{{ $account->name }}</td><td class="p-4" dir="ltr">{{ $account->email }}</td><td class="p-4" dir="ltr">{{ $account->mobile ?: '—' }}</td><td class="p-4">@foreach($account->roles as $role)<span class="m-1 inline-block rounded-lg bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">{{ $role->system ? __('staff.role_'.$role->name) : $role->label }}</span>@endforeach</td><td class="p-4">{{ __('staff.'.($account->active ? 'active' : 'inactive')) }}</td><td class="p-4"><a class="text-teal-600 dark:text-teal-300" href="{{ route('users.edit', $account) }}">{{ __('staff.edit') }}</a></td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">{{ __('staff.empty') }}</td></tr>@endforelse</tbody>
    </table></div><div class="mt-5">{{ $users->links() }}</div>
</x-staff-page>
