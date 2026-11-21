@php
    $today = now('Asia/Tehran');
    $label = app()->getLocale() === 'fa'
        ? \App\Services\PersianDate::formatPersianDigits($today).'، '.$today->locale('fa')->translatedFormat('l')
        : $today->locale('en')->translatedFormat('Y/m/d, l');
@endphp
<span {{ $attributes->merge(['class' => 'font-medium tabular-nums']) }} data-current-date>{{ $label }}</span>
