<form method="POST" action="{{ route('preferences.update') }}" data-preference-form class="flex flex-wrap items-end gap-4">
    @csrf @method('PATCH')
    <label class="flex min-w-36 flex-1 flex-col gap-2 text-sm"><span data-i18n="language">{{ __('ui.language') }}</span><select name="locale" class="input"><option value="fa" @selected(app()->getLocale() === 'fa')>فارسی</option><option value="en" @selected(app()->getLocale() === 'en')>English</option></select></label>
    <label class="flex min-w-36 flex-1 flex-col gap-2 text-sm"><span data-i18n="theme">{{ __('ui.theme') }}</span><select name="theme" class="input">@foreach(['light','dark','system'] as $theme)<option value="{{ $theme }}" @selected($currentTheme === $theme) data-i18n="{{ $theme }}">{{ __('ui.'.$theme) }}</option>@endforeach</select></label>
    <button class="button" data-i18n="save">{{ __('ui.save') }}</button>
    <p data-preference-status class="w-full text-sm text-teal-700 dark:text-teal-300" role="status">{{ session('status') }}</p>
    @if($errors->any())<p class="w-full text-sm text-red-600">{{ $errors->first() }}</p>@endif
</form>
