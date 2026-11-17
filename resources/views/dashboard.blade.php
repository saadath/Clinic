<x-layout :title="__('ui.dashboard')">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4"><div><p class="mb-2 text-sm text-slate-500"><span data-i18n="welcome">{{ __('ui.welcome') }}</span> {{ auth()->user()->name }}</p><h1 class="text-2xl font-bold md:text-3xl" data-i18n="overview">{{ __('ui.overview') }}</h1><p class="mt-3 text-sm text-slate-500" data-i18n="overview_intro">{{ __('ui.overview_intro') }}</p></div><div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900"><span data-current-date>{{ now()->locale(app()->getLocale())->translatedFormat('l') }}</span></div></div>
    <section class="relative overflow-hidden rounded-3xl bg-teal-950 p-7 text-white md:p-10"><div class="absolute -end-12 -top-12 size-64 rounded-full border-[35px] border-white/5"></div><div class="relative max-w-xl"><span class="mb-5 inline-flex rounded-full bg-teal-900 px-3 py-1 text-xs text-teal-200" data-i18n="personal_space">{{ __('ui.personal_space') }}</span><h2 class="text-2xl font-semibold leading-relaxed" data-i18n="account">{{ __('ui.account') }}</h2><p class="mt-3 text-sm leading-7 text-teal-100/70" data-i18n="account_description">{{ __('ui.account_description') }}</p><a href="{{ route('settings') }}" class="mt-7 inline-flex items-center gap-3 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-teal-950"><span data-i18n="settings">{{ __('ui.settings') }}</span><x-icon name="arrow"/></a></div></section>
    <section class="mt-10">
        <h2 class="font-semibold" data-i18n="clinic_tools">{{ __('ui.clinic_tools') }}</h2>
        <p class="mt-2 text-sm text-slate-500" data-i18n="clinic_tools_intro">{{ __('ui.clinic_tools_intro') }}</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach(['appointments' => ['ability' => 'appointments.view', 'icon' => 'calendar'], 'patients' => ['ability' => 'patients.view', 'icon' => 'users'], 'forms' => ['ability' => 'forms.view', 'icon' => 'file']] as $section => $tool)
                @can($tool['ability'])
                    <a href="{{ route($section.'.index') }}" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-teal-500 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-teal-500">
                        <span class="mb-6 flex size-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><x-icon :name="$tool['icon']"/></span>
                        <h3 class="text-sm font-semibold" data-i18n="{{ $section }}">{{ __('ui.'.$section) }}</h3>
                        <p class="mt-3 text-sm text-slate-500" data-i18n="{{ $section }}_intro">{{ __('ui.'.$section.'_intro') }}</p>
                        <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 dark:text-teal-300"><span data-i18n="open_section">{{ __('ui.open_section') }}</span><x-icon name="arrow"/></span>
                    </a>
                @endcan
            @endforeach
        </div>
    </section>
    <section class="mt-10">
        <h2 class="font-semibold" data-i18n="next_features">{{ __('ui.next_features') }}</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach(['medical_records' => 'phase_six'] as $label => $phase)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold" data-i18n="{{ $label }}">{{ __('ui.'.$label) }}</h3>
                    <p class="mt-3 text-sm text-slate-500" data-i18n="{{ $phase }}">{{ __('ui.'.$phase) }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layout>
