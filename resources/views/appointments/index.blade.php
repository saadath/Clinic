<x-staff-page :title="__('appointments.title')">
    @can('schedules.manage')<a class="button mb-5" href="{{ route('schedules.index', ['doctor_id' => $doctor?->id]) }}">{{ __('appointments.schedules') }}</a>@endcan
    <form method="GET" class="panel mb-5 grid gap-4 md:grid-cols-3">
        <label class="grid gap-2 text-sm">{{ __('appointments.doctor_id') }}<select class="input" name="doctor_id">@foreach($doctors as $item)<option value="{{ $item->id }}" @selected($item->id === $doctor?->id)>{{ $item->user->name }}</option>@endforeach</select></label>
        <x-persian-date name="date" :label="__('appointments.date')" :value="$dateInput" required/>
        <label class="grid gap-2 text-sm">{{ __('appointments.title') }}<select class="input" name="view">@foreach(['day','week','month'] as $view)<option value="{{ $view }}" @selected($mode === $view)>{{ __('appointments.'.$view) }}</option>@endforeach</select></label>
        <label class="grid gap-2 text-sm md:col-span-2">{{ __('appointments.search') }}<input class="input" name="q" value="{{ request('q') }}"></label>
        <button class="button self-end">{{ __('appointments.filter') }}</button>
    </form>
    @if(!$doctor)<p class="panel">{{ __('appointments.no_doctor') }}</p>@else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            @foreach(['previous' => -1, 'next' => 1] as $label => $direction)
                <a class="button" href="{{ route('appointments.index', ['doctor_id' => $doctor->id, 'view' => $mode, 'date' => \App\Services\PersianDate::format($direction < 0 ? $days->first()->subDay() : $days->last()->addDay())]) }}">{{ __('appointments.'.$label) }}</a>
            @endforeach
        </div>
        <div @class(['grid gap-3', 'md:grid-cols-2 xl:grid-cols-7' => $mode !== 'day'])>
            @foreach($days as $day)
                <section class="panel min-w-0 !p-3">
                    <a class="mb-3 block text-sm font-semibold text-teal-700 dark:text-teal-300" href="{{ route('appointments.index', ['doctor_id' => $doctor->id, 'date' => \App\Services\PersianDate::format($day)]) }}">{{ __('appointments.weekdays')[$day->dayOfWeek] }} {{ \App\Services\PersianDate::format($day) }}</a>
                    <div class="grid gap-3">@forelse($grouped->get($day->toDateString(), collect()) as $appointment)
                        <a class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" href="{{ route('appointments.show', $appointment) }}">
                            <span class="block font-semibold">{{ $appointment->starts_at->setTimezone('Asia/Tehran')->format('H:i') }} — {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</span>
                            <span class="mt-2 block text-xs text-slate-500">{{ __('appointments.'.$appointment->status) }} @if($queue->has($appointment->id)) · {{ __('appointments.queue') }} {{ $queue[$appointment->id]->queue_number }}@endif</span>
                        </a>
                    @empty<p class="text-xs text-slate-500">{{ __('appointments.empty') }}</p>@endforelse</div>
                </section>
            @endforeach
        </div>
        @if($mode === 'day')
            <section class="panel mt-5"><h2 class="mb-4 font-semibold">{{ __('appointments.waiting') }}</h2><ol class="grid gap-3">@foreach($waiting as $item)<li><a class="text-sm text-teal-700 dark:text-teal-300" href="{{ route('appointments.show',$item) }}">{{ __('appointments.queue') }} {{ $queue->get($item->id)?->queue_number }} · {{ $item->patient->first_name }} {{ $item->patient->last_name }} · {{ __('appointments.'.$item->status) }}</a></li>@endforeach</ol></section>
            <section class="panel mt-5"><h2 class="mb-4 font-semibold">{{ __('appointments.slots') }}</h2><div class="flex flex-wrap gap-2">@foreach($slots as $slot)<span class="rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-700">{{ $slot['start']->format('H:i') }} · {{ __('appointments.booked') }} {{ $slot['booked'] }}/{{ $slot['capacity'] }}</span>@endforeach</div></section>
            @can('appointments.manage')
                <form method="POST" action="{{ route('appointments.store') }}" class="panel mt-5 grid gap-4 md:grid-cols-2">
                    @csrf<input type="hidden" name="doctor_id" value="{{ $doctor->id }}"><input type="hidden" name="date_calendar" value="persian">
                    <h2 class="font-semibold md:col-span-2">{{ __('appointments.book') }}</h2>
                    <label class="grid gap-2 text-sm">{{ __('appointments.patient_id') }}<select class="input" name="patient_id" required><option value="">{{ __('appointments.select_patient') }}</option>@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->first_name }} {{ $patient->last_name }} — {{ $patient->national_id }}</option>@endforeach</select></label>
                    <x-persian-date name="starts_at" :label="__('appointments.starts_at')" :value="\App\Services\PersianDate::format($date->setTime(9,0), true)" required with-time/>
                    <label class="grid gap-2 text-sm">{{ __('appointments.visit_type') }}<input class="input" name="visit_type" required maxlength="100" value="{{ old('visit_type', __('appointments.consultation')) }}"></label>
                    <label class="grid gap-2 text-sm">{{ __('appointments.notes') }}<textarea class="input" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                    @can('appointments.override')<label class="grid gap-2 text-sm">{{ __('appointments.override_reason') }}<input class="input" name="override_reason" maxlength="500" value="{{ old('override_reason') }}"></label>@endcan
                    <button class="button justify-self-start">{{ __('appointments.book') }}</button>
                </form>
            @endcan
        @endif
    @endif
</x-staff-page>
