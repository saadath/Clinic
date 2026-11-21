<!doctype html>
@php
    $isEnglish = app()->getLocale() === 'en';
    $formatDate = fn ($date) => $isEnglish ? $date->format('Y-m-d') : \App\Services\PersianDate::formatPersianDigits($date);
    $persianDigits = array_combine(str_split('0123456789'), mb_str_split('۰۱۲۳۴۵۶۷۸۹'));
@endphp
<html lang="{{ $isEnglish ? 'en' : 'fa' }}" dir="{{ $isEnglish ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('medical_records.report_title') }}</title>
    @vite('resources/css/app.css')
    <style>
        @counter-style report-persian-digits {
            system: numeric;
            symbols: '۰' '۱' '۲' '۳' '۴' '۵' '۶' '۷' '۸' '۹';
        }
        @page {
            size: A4 portrait;
            margin: 11mm 13mm 12mm;
            @bottom-center {
                content: counter(page, {{ $isEnglish ? 'decimal' : 'report-persian-digits' }});
                font: 8.5pt 'Vazirmatn Variable', sans-serif;
                color: #43585c;
            }
        }
        body { margin: 0; background: #edf2f3; color: #182c32; font: 10pt/1.55 'Vazirmatn Variable', sans-serif; }
        .report-sheet { width: min(100%, 210mm); margin: 0 auto 1.5rem; padding: 11mm 13mm; background: #fff; }
        .report-layout { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .report-layout > :is(thead, tbody) > tr > td { padding: 0; vertical-align: top; }
        .report-brand { display: flex; align-items: center; gap: .65rem; padding-block: 0 2mm; border-bottom: 2px solid #0f766e; }
        .report-brand svg { color: #0f766e; width: 1.75rem; height: 1.75rem; flex: none; }
        .report-brand strong { display: block; font-size: 15pt; line-height: 1.3; }
        .report-brand span { color: #43585c; }
        .report-section { margin-block: 3mm; }
        .report-heading { color: #115e59; font-weight: 750; font-size: 11pt; margin-block-end: 1mm; break-after: avoid; }
        .report-details { border-block: 1px solid #cad9da; padding-block: 1.5mm; }
        .report-details > div { overflow-wrap: anywhere; }
        .report-details dt { color: #43585c; font-size: 8.5pt; }
        .report-details dd { font-weight: 550; white-space: pre-wrap; }
        .report-visit { border-top: 1px solid #cad9da; padding-block: 1.8mm; }
        .report-visit-heading { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem; font-weight: 750; color: #115e59; break-after: avoid; }
        .report-direction { text-align: start; overflow-wrap: anywhere; }
        .report-form { margin-block-start: 1.1mm; }
        .report-form h4 { font-weight: 750; break-after: avoid; }
        .report-answer { text-align: start; white-space: pre-wrap; overflow-wrap: anywhere; }
        .report-answer dt { font-weight: 650; }
        .report-sheet .clinical-content { line-height: 1.55; }
        .report-sheet .clinical-content p { margin-block: .2em; }
        .report-sheet .clinical-content table { table-layout: fixed; margin-block: .4em; }
        .report-sheet .clinical-content :is(th, td) { padding: .2em .4em; }
        .report-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; margin-block-start: 3mm; padding-block-start: 1.5mm; border-top: 2px solid #0f766e; break-inside: avoid; }
        .report-footer strong { display: block; }
        @media screen and (max-width: 600px) { .report-sheet { padding: 1rem; } }
        @media print {
            body { background: #fff; }
            .report-tools { display: none !important; }
            .report-sheet { width: auto; margin: 0; padding: 0; }
            .report-layout > thead { display: table-header-group; break-inside: avoid; }
            .report-layout > thead .report-brand { margin-block-end: 2mm; }
            .report-heading, .report-visit-heading, .report-form h4 { page-break-after: avoid; }
            .report-sheet p, .report-answer { orphans: 2; widows: 2; }
        }
    </style>
</head>
<body data-print-report="{{ $print ? '1' : '0' }}">
    <nav class="report-tools mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4 py-3">
        <a href="{{ route('medical-records.index', $patient) }}" class="font-semibold text-teal-800 underline">{{ __('patients.back') }}</a>
        <button type="button" id="print-report" class="button gap-2"><x-icon name="printer" class="size-4"/>{{ __('medical_records.report_pdf') }}</button>
    </nav>
    <main class="report-sheet">
        <table class="report-layout" role="presentation">
            <thead>
                <tr><td>
                    <header class="report-brand">
                        <x-icon/>
                        <div><strong>{{ __('medical_records.report_clinic') }}</strong><span>{{ __('medical_records.report_title') }}</span></div>
                    </header>
                </td></tr>
            </thead>
            <tbody>
                <tr><td>
                    <section class="report-section">
                        <h1 class="report-heading">{{ __('medical_records.report_patient') }}: <bdi>{{ $patient->first_name }} {{ $patient->last_name }}</bdi></h1>
                        <dl class="report-details grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-4 print:grid-cols-4">
                            @foreach([
                                __('medical_records.age') => $patient->birth_date?->age,
                                __('patients.birth_date') => $patient->birth_date ? $formatDate($patient->birth_date) : null,
                                __('patients.sex') => $patient->sex ? __('patients.'.$patient->sex) : null,
                                __('patients.national_id') => $patient->national_id,
                                __('patients.mobile') => $patient->mobile,
                                __('patients.email') => $patient->email,
                                __('patients.insurance_name') => $patient->insurance_name,
                                __('patients.insurance_number') => $patient->insurance_number,
                                __('patients.occupation') => $patient->occupation,
                                __('patients.marital_status') => $patient->marital_status ? __('patients.'.$patient->marital_status) : null,
                            ] as $label => $value)
                                @if($value !== null && $value !== '')
                                    @if(in_array($label, [__('patients.national_id'), __('patients.mobile')], true))
                                        <div><dt>{{ $label }}</dt><dd><bdi dir="ltr">{{ strtr((string) $value, $persianDigits) }}</bdi></dd></div>
                                    @else
                                        <div><dt>{{ $label }}</dt><dd dir="auto">{{ $value }}</dd></div>
                                    @endif
                                @endif
                            @endforeach
                            @if($patient->address)
                                <div class="col-span-full"><dt>{{ __('patients.address') }}</dt><dd dir="auto">{{ $patient->address }}</dd></div>
                            @endif
                        </dl>
                    </section>
                    <section class="report-section">
                        <h2 class="report-heading">{{ __('medical_records.chronic_summary') }}</h2>
                        @if($summaryHtml !== '')
                            <div class="clinical-content" lang="{{ $summaryLanguage }}" dir="{{ $summaryLanguage === 'en' ? 'ltr' : 'rtl' }}">{!! $summaryHtml !!}</div>
                        @else
                            <p>{{ __('medical_records.no_summary') }}</p>
                        @endif
                    </section>
                    <h2 class="report-heading">{{ __('medical_records.encounters_title') }}</h2>
                    @if($visits->isEmpty())
                        <p>{{ __('medical_records.no_encounters') }}</p>
                    @endif
                </td></tr>
                @foreach($visits as ['encounter' => $encounter, 'noteHtml' => $noteHtml, 'forms' => $forms])
                    <tr><td>
                        <article class="report-visit">
                            <h3 class="report-visit-heading"><bdi>{{ $formatDate($encounter->created_at) }}</bdi></h3>
                            @if($noteHtml !== null)
                                <div class="clinical-content" lang="{{ $encounter->note_language ?? 'fa' }}" dir="{{ $encounter->note_language === 'en' ? 'ltr' : 'rtl' }}">{!! $noteHtml !!}</div>
                            @else
                                <p class="report-direction whitespace-pre-wrap" dir="auto">{{ $encounter->note }}</p>
                            @endif
                            @foreach($forms as $form)
                                <div class="report-form">
                                    <dl>
                                        @foreach($form['fields'] as $field)
                                            <div class="report-answer" dir="auto"><dt class="inline">{{ $field['label'] }}:</dt> <dd class="inline"><bdi>{{ $field['text'] }}</bdi></dd></div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endforeach
                            @foreach($encounter->amendments->sortBy('id') as $amendment)
                                <section class="report-form">
                                    <h4>{{ __('medical_records.amend') }} · <bdi>{{ $formatDate($amendment->created_at) }}</bdi> · <bdi>{{ $amendment->author->name }}</bdi></h4>
                                    <p class="report-direction whitespace-pre-wrap" dir="auto">{{ $amendment->body }}</p>
                                </section>
                            @endforeach
                        </article>
                    </td></tr>
                @endforeach
                <tr><td>
                    <footer class="report-footer">
                        <div>
                            <span>{{ __('medical_records.report_doctor') }}</span>
                            @forelse($doctors as $doctor)
                                <div><strong dir="auto">{{ $doctor->user->name }}</strong>{{ __('staff.medical_council_number') }}: <bdi>{{ $doctor->medical_council_number ?? '—' }}</bdi></div>
                            @empty
                                <strong>—</strong>
                            @endforelse
                        </div>
                        <div>{{ __('medical_records.report_date') }}<strong><bdi>{{ $formatDate($issuedOn) }}</bdi></strong></div>
                    </footer>
                </td></tr>
            </tbody>
        </table>
    </main>
    <script>
        async function printReport() {
            await document.fonts.ready;
            window.print();
        }
        document.getElementById('print-report').addEventListener('click', printReport);
        if (document.body.dataset.printReport === '1') {
            window.addEventListener('load', printReport, { once: true });
        }
    </script>
</body>
</html>
