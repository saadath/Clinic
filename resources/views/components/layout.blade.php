@props(['title'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" data-theme="{{ $currentTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ __('ui.brand') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        window.applyClinicTheme = function (theme) {
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark.matches));
        };
        applyClinicTheme(document.documentElement.dataset.theme);
        prefersDark.addEventListener('change', () => applyClinicTheme(document.documentElement.dataset.theme));
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100">
<div x-data="{ menu: false }" class="min-h-screen">
    @auth
        <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"></div>
        <aside :class="menu ? 'flex' : 'hidden lg:flex'" class="fixed inset-y-0 start-0 z-40 w-64 flex-col overflow-hidden border-e border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-2xl bg-teal-600 text-white"><x-icon name="stethoscope" class="size-6"/></span>
                <span><strong class="block text-xl" data-i18n="brand">{{ __('ui.brand') }}</strong><span class="text-[11px] text-slate-500" data-i18n="tagline">{{ __('ui.tagline') }}</span></span>
            </a>
            <button type="button" @click="menu = false" class="mt-5 shrink-0 text-start text-sm lg:hidden" data-i18n="close">{{ __('ui.close') }}</button>
            <nav class="mt-10 min-h-0 flex-1 overflow-y-auto overscroll-contain pe-1 pb-4" aria-label="{{ __('ui.navigation') }}" tabindex="0">
                <a href="{{ route('dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('dashboard'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="home"/><span data-i18n="dashboard">{{ __('ui.dashboard') }}</span></a>
                @can('patients.view')<a href="{{ route('patients.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('patients.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('patients.*')])><x-icon name="users"/><span data-staff-i18n="patients">{{ __('staff.patients') }}</span></a>@endcan
                @can('forms.view')<a href="{{ route('forms.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('forms.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('forms.*')])><x-icon name="file"/><span data-staff-i18n="forms">{{ __('staff.forms') }}</span></a>@endcan
                <a href="{{ route('settings') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('settings'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('settings')]) @if(request()->routeIs('settings')) aria-current="page" @endif><x-icon name="settings"/><span data-i18n="settings">{{ __('ui.settings') }}</span></a>
                @if(auth()->user()->hasRole('doctor'))<a href="{{ route('doctor-settings.edit') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('doctor-settings.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('doctor-settings.*')])><x-icon name="settings"/>{{ __('medical_records.doctor_settings') }}</a>@endif
                @can('doctors.view')<a href="{{ route('doctors.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs('doctors.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs('doctors.*')])><x-icon name="shield"/><span data-staff-i18n="doctors">{{ __('staff.doctors') }}</span></a>@endcan
                @foreach(['users' => 'users.manage', 'roles' => 'roles.manage', 'assignments' => 'assignments.manage'] as $section => $ability)
                    @can($ability)<a href="{{ route($section.'.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs($section.'.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs($section.'.*')])><x-icon name="users"/><span data-staff-i18n="{{ $section }}">{{ __('staff.'.$section) }}</span></a>@endcan
                @endforeach
            </nav>
            <div class="mt-3 shrink-0 border-t border-slate-100 pt-5 dark:border-slate-800">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-slate-400" dir="ltr">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<button class="text-sm text-slate-500 hover:text-red-600" data-i18n="logout">{{ __('ui.logout') }}</button></form>
            </div>
        </aside>
        <div class="lg:ps-64">
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900 md:px-10">
                <div class="flex min-w-0 items-center gap-3"><button @click="menu = !menu" class="rounded-lg p-2 lg:hidden" aria-label="{{ __('ui.open_menu') }}" :aria-expanded="menu"><x-icon name="menu"/></button><span class="truncate text-sm font-medium">{{ auth()->user()->name }}</span></div>
                <div class="flex shrink-0 items-center gap-2 rounded-full border border-teal-100 bg-teal-50/70 px-3 py-2 text-xs text-teal-800 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-200">
                    <x-icon name="calendar" class="size-4"/>
                    <x-today-date/>
                    <form method="POST" action="{{ route('logout') }}" class="flex">
                        @csrf
                        <button type="submit" class="group flex size-8 items-center justify-center rounded-full text-slate-500 transition hover:bg-white hover:text-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-400" aria-label="{{ __('ui.logout') }}" title="{{ __('ui.logout') }}">
                            <x-icon name="logout" class="size-4 transition-transform group-hover:-translate-x-0.5"/>
                        </button>
                    </form>
                </div>
            </header>
            <main class="mx-auto max-w-7xl p-5 py-9 md:p-10">{{ $slot }}</main>
        </div>
    @else
        {{ $slot }}
    @endauth
</div>
</body>
</html>
