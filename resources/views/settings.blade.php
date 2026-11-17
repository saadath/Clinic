<x-layout :title="__('ui.settings')">
    <h1 class="text-2xl font-bold" data-i18n="settings">{{ __('ui.settings') }}</h1>
    <section class="mt-7 max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 md:p-8"><h2 class="font-semibold" data-i18n="preferences">{{ __('ui.preferences') }}</h2><p class="mb-7 mt-2 text-sm text-slate-500" data-i18n="preferences_intro">{{ __('ui.preferences_intro') }}</p><x-preferences/></section>
</x-layout>
