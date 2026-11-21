<x-staff-page :title="__('patients.title')" :create-route="auth()->user()->can('patients.create') ? 'patients.create' : null">
    <form method="GET" action="{{ route('patients.index', absolute: false) }}" class="mb-6 flex flex-wrap gap-3">
        <div class="relative min-w-0 flex-1">
            <input class="input pe-12" name="q" value="{{ request('q') }}" placeholder="{{ __('patients.search') }}" aria-label="{{ __('patients.search') }}" autocomplete="off">
            @if(request()->filled('q'))
                <a href="{{ route('patients.index', absolute: false) }}" aria-label="{{ __('patients.clear_search') }}" title="{{ __('patients.clear_search') }}" class="absolute inset-y-0 end-2 inline-flex size-10 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                    <x-icon name="cross" class="size-4"/>
                </a>
            @endif
        </div>
        <button class="button" type="submit">{{ __('staff.search') }}</button>
    </form>
    @include('patients.results')
</x-staff-page>
