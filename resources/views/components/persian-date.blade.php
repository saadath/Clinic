@props(['name', 'value' => '', 'required' => false, 'withTime' => false, 'label'])
@php
    $displayValue = old($name, $value);
    if (is_string($displayValue) && preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/D', $displayValue)) {
        $displayValue = \App\Services\PersianDate::format(\Carbon\Carbon::parse($displayValue, 'Asia/Tehran'), $withTime);
    }
@endphp
<div class="grid gap-2 text-sm" x-data="persianDatePicker" data-value="{{ $displayValue }}" data-with-time="{{ $withTime ? '1' : '0' }}">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
    <div class="flex gap-2">
        <input id="{{ $name }}" class="input min-w-0 flex-1" name="{{ $name }}" x-model="value" dir="ltr" placeholder="{{ $withTime ? '1405/01/01 09:00' : '1405/01/01' }}" @required($required) aria-describedby="{{ $name }}-hint {{ $name }}-error" @if($errors->has($name)) aria-invalid="true" @endif>
        <button type="button" class="rounded-xl border border-slate-300 px-3 dark:border-slate-700" @click="open = !open" :aria-expanded="open" aria-label="{{ __('staff.choose_date') }}"><x-icon name="calendar"/></button>
    </div>
    <div x-show="open" x-cloak class="grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
        <div class="flex gap-2" dir="ltr">
            <input class="input min-w-0 flex-1" type="number" x-model.number="year" min="1200" max="1600" aria-label="{{ __('staff.year') }}">
            <select class="input min-w-0 flex-1" x-model.number="month" aria-label="{{ __('staff.month') }}"><template x-for="(title, index) in months" :key="index"><option :value="index + 1" x-text="title"></option></template></select>
            <select class="input min-w-0 flex-1" x-model.number="day" aria-label="{{ __('staff.day') }}"><template x-for="number in days()" :key="number"><option :value="number" x-text="number"></option></template></select>
        </div>
        @if($withTime)<input class="input" type="time" x-model="time" aria-label="{{ __('staff.time') }}">@endif
        <button type="button" class="button justify-self-start" @click="selectDate()">{{ __('staff.apply_date') }}</button>
    </div>
    <span id="{{ $name }}-hint" class="text-xs text-slate-500">{{ $withTime ? __('staff.persian_datetime_hint') : __('patients.date_hint') }}</span>
    @error($name)<span id="{{ $name }}-error" class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span>@enderror
</div>
