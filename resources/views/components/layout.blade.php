@props(['title'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" data-theme="{{ $currentTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ __('ui.brand') }}</title>
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
        <aside :class="menu ? 'flex' : 'hidden lg:flex'" class="fixed inset-y-0 start-0 z-40 w-64 flex-col border-e border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-2xl bg-teal-600 text-white"><x-icon class="size-6"/></span>
                <span><strong class="block text-xl" data-i18n="brand">{{ __('ui.brand') }}</strong><span class="text-[11px] text-slate-500" data-i18n="tagline">{{ __('ui.tagline') }}</span></span>
            </a>
            <button type="button" @click="menu = false" class="mt-5 text-start text-sm lg:hidden" data-i18n="close">{{ __('ui.close') }}</button>
            <p class="mb-3 mt-12 text-xs text-slate-400" data-i18n="workspace">{{ __('ui.workspace') }}</p>
            <nav class="flex flex-col gap-2" aria-label="{{ __('ui.navigation') }}">
                @can('appointments.view')<a href="{{ route('appointments.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-teal-700 dark:text-teal-300"><x-icon name="calendar"/>{{ __('appointments.title') }}</a>@endcan
                @foreach(['dashboard' => 'home', 'settings' => 'settings'] as $route => $icon)
                    <a href="{{ route($route) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs($route), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif><x-icon :name="$icon"/><span data-i18n="{{ $route }}">{{ __('ui.'.$route) }}</span></a>
                @endforeach
                @foreach(['forms' => 'forms.view', 'patients' => 'patients.view', 'users' => 'users.manage', 'roles' => 'roles.manage', 'doctors' => 'doctors.view', 'assignments' => 'assignments.manage'] as $section => $ability)
                    @can($ability)<a href="{{ route($section.'.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium', 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' => request()->routeIs($section.'.*'), 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800' => !request()->routeIs($section.'.*')])><x-icon name="users"/><span data-staff-i18n="{{ $section }}">{{ __('staff.'.$section) }}</span></a>@endcan
                @endforeach
            </nav>
            <div class="mt-auto border-t border-slate-100 pt-5 dark:border-slate-800">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-slate-400" dir="ltr">{{ auth()->user()->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<button class="text-sm text-slate-500 hover:text-red-600" data-i18n="logout">{{ __('ui.logout') }}</button></form>
            </div>
        </aside>
        <div class="lg:ps-64">
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900 md:px-10">
                <div class="flex min-w-0 items-center gap-3"><button @click="menu = !menu" class="rounded-lg p-2 lg:hidden" aria-label="{{ __('ui.open_menu') }}" :aria-expanded="menu"><x-icon name="menu"/></button><span class="truncate text-sm font-medium">{{ auth()->user()->name }}</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950" data-i18n="logout">{{ __('ui.logout') }}</button></form>
                <span class="flex items-center gap-2 text-xs text-slate-500"><span class="size-2 rounded-full bg-teal-500"></span><span data-i18n="secure_session">{{ __('ui.secure_session') }}</span></span>
            </header>
            <main class="mx-auto max-w-7xl p-5 py-9 md:p-10">{{ $slot }}</main>
        </div>
    @else
        {{ $slot }}
    @endauth
</div>
</body>
</html>
