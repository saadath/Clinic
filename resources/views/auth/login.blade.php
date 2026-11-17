<x-layout :title="__('ui.login')">
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="flex flex-col justify-between bg-teal-950 p-8 text-white md:p-14 lg:p-16">
            <div class="flex items-center gap-3"><span class="flex size-12 items-center justify-center rounded-2xl bg-white/10"><x-icon class="size-7"/></span><strong class="text-2xl" data-i18n="brand">{{ __('ui.brand') }}</strong></div>
            <div class="max-w-lg py-12 lg:py-20"><span class="mb-7 block text-xs tracking-widest text-teal-300">CLINIC WORKSPACE</span><h1 class="text-3xl font-semibold leading-relaxed md:text-4xl md:leading-relaxed" data-i18n="care_heading">{{ __('ui.care_heading') }}</h1><p class="mt-6 text-sm leading-8 text-teal-100/70" data-i18n="care_description">{{ __('ui.care_description') }}</p></div>
            <p class="flex items-center gap-2 text-xs text-teal-200/70"><x-icon name="shield"/><span data-i18n="staff_only">{{ __('ui.staff_only') }}</span></p>
        </section>
        <section class="flex items-center justify-center p-6 py-14 md:p-14">
            <div class="w-full max-w-md">
                <p class="mb-3 text-xs font-semibold text-teal-600" data-i18n="tagline">{{ __('ui.tagline') }}</p>
                <h2 class="text-2xl font-bold" data-i18n="login">{{ __('ui.login') }}</h2><p class="mt-3 text-sm leading-7 text-slate-500" data-i18n="login_intro">{{ __('ui.login_intro') }}</p>
                <form method="POST" action="{{ route('login.store') }}" class="mt-9 flex flex-col gap-5">
                    @csrf
                    <label class="flex flex-col gap-2 text-sm"><span data-i18n="email">{{ __('ui.email') }}</span><input name="email" type="email" value="{{ old('email') }}" class="input" dir="ltr" autocomplete="username" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></label>
                    @error('email')<p id="email-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <label class="flex flex-col gap-2 text-sm"><span data-i18n="password">{{ __('ui.password') }}</span><input name="password" type="password" class="input" dir="ltr" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror></label>
                    @error('password')<p id="password-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                    <button class="button mt-2 w-full" data-i18n="sign_in">{{ __('ui.sign_in') }}</button>
                </form>
                <p class="my-7 text-xs leading-6 text-slate-400" data-i18n="no_registration">{{ __('ui.no_registration') }}</p>
                <div class="border-t border-slate-200 pt-6 dark:border-slate-800"><x-preferences/></div>
            </div>
        </section>
    </main>
</x-layout>
