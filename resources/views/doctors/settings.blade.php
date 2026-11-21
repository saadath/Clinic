<x-staff-page :title="__('medical_records.doctor_settings')">
    <form method="POST" action="{{ route('doctor-settings.update') }}" class="panel grid max-w-2xl gap-5">
        @csrf @method('PUT')
        <label class="grid gap-3 text-sm">{{ __('medical_records.chart_language') }}
            <select class="input" name="chart_language"><option value="fa" @selected(old('chart_language', $doctor->chart_language) === 'fa')>فارسی — راست به چپ</option><option value="en" @selected(old('chart_language', $doctor->chart_language) === 'en')>English — Left to right</option></select>
        </label>
        <p class="text-xs text-slate-500">{{ __('medical_records.language_hint') }}</p>
        <label class="grid gap-3 text-sm">{{ __('medical_records.chronic_summary_font_size') }}
            <input class="input" type="range" name="chronic_summary_font_size" min="8" max="26" step="1" value="{{ old('chronic_summary_font_size', $doctor->chronic_summary_font_size) }}" oninput="this.nextElementSibling.value = `${this.value} px`">
            <output class="text-xs text-slate-500">{{ old('chronic_summary_font_size', $doctor->chronic_summary_font_size ?? 14) }} px</output>
        </label>
        <label class="grid gap-3 text-sm">{{ __('medical_records.default_theme') }}
            <select class="input" name="theme"><option value="light" @selected(old('theme', auth()->user()->theme) === 'light')>{{ __('ui.light') }}</option><option value="dark" @selected(old('theme', auth()->user()->theme) === 'dark')>{{ __('ui.dark') }}</option></select>
        </label>
        <button class="button justify-self-start">{{ __('patients.save') }}</button>
    </form>
</x-staff-page>
