<x-staff-page :title="__('forms.title')" :create-route="auth()->user()->can('forms.manage') ? 'forms.create' : null">
    <div class="grid gap-5 lg:grid-cols-2">@forelse($templates as $template)<article class="panel">
        <h2 class="font-bold">{{ $template->name }}</h2><p class="my-3 text-sm text-slate-500">{{ $template->doctor->user->name }} · {{ __('forms.'.($template->active ? 'active' : 'draft')) }}</p>
        <div class="flex flex-wrap items-center gap-3">
            @can('forms.manage')
                <a class="button" href="{{ route('forms.edit', $template) }}">{{ __('forms.edit') }}</a>
                @if(auth()->user()->can('forms.manage-all') || $template->owner_doctor_id === auth()->user()->doctor?->id)
                    <form method="POST" action="{{ route('forms.destroy', $template) }}" data-confirm="{{ __('forms.delete_confirm', ['name' => $template->name]) }}" onsubmit="return confirm(this.dataset.confirm)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/60">
                            <x-icon name="trash" class="size-4"/>
                            {{ __('forms.delete') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
        @if($template->currentVersion)<p class="mt-5 text-sm text-slate-500">{{ __('forms.'.($template->currentVersion->status === 'published' ? 'published_status' : 'draft')) }} · <a class="text-teal-700 dark:text-teal-300" href="{{ route('form-versions.show', $template->currentVersion) }}">{{ __('forms.preview') }}</a></p>@endif
    </article>@empty<p>{{ __('forms.empty') }}</p>@endforelse</div><div class="mt-5">{{ $templates->links() }}</div>
    @if($shared->isNotEmpty())
        <h2 class="my-6 font-bold">{{ __('forms.shared') }}</h2>
        @foreach($shared as $template)
            <form class="panel mb-4 flex flex-wrap gap-3" method="POST" action="{{ route('forms.copy', $template) }}">
                @csrf
                <span>{{ $template->name }}</span>
                <input type="hidden" name="name" value="{{ $template->name }} (copy)">
                <input type="hidden" name="doctor_id" value="{{ auth()->user()->doctor?->id }}">
                <button class="button">{{ __('forms.copy') }}</button>
            </form>
        @endforeach
    @endif
</x-staff-page>
