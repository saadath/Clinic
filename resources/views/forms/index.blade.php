<x-staff-page :title="__('forms.title')" :create-route="auth()->user()->can('forms.manage') ? 'forms.create' : null">
    <div class="grid gap-5 lg:grid-cols-2">@forelse($templates as $template)<article class="panel">
        <h2 class="font-bold">{{ $template->name }}</h2><p class="my-3 text-sm text-slate-500">{{ $template->doctor->user->name }} · {{ __('forms.'.($template->active ? 'active' : 'draft')) }}</p>
        <div class="flex flex-wrap gap-3">@can('forms.manage')<form method="POST" action="{{ route('forms.edit', $template) }}">@csrf<button class="button">{{ __('forms.edit') }}</button></form>@endcan</div>
        <ul class="mt-5 grid gap-2 text-sm">@foreach($template->versions as $version)<li><a class="text-teal-700 dark:text-teal-300" href="{{ route('form-versions.show', $version) }}">{{ __('forms.version') }} {{ $version->version_number }} — {{ __('forms.'.($version->status === 'published' ? 'published_status' : 'draft')) }}</a></li>@endforeach</ul>
    </article>@empty<p>{{ __('forms.empty') }}</p>@endforelse</div><div class="mt-5">{{ $templates->links() }}</div>
    @if($shared->isNotEmpty())<h2 class="my-6 font-bold">{{ __('forms.shared') }}</h2>@foreach($shared as $template)<form class="panel mb-4 flex flex-wrap gap-3" method="POST" action="{{ route('forms.copy', $template) }}">@csrf<span>{{ $template->name }}</span><input type="hidden" name="name" value="{{ $template->name }} (copy)"><input type="hidden" name="doctor_id" value="{{ auth()->user()->doctor?->id }}"><button class="button">{{ __('forms.copy') }}</button></form>@endforeach@endif
</x-staff-page>
