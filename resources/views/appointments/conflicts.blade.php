<x-staff-page title="نوبت‌های نیازمند رسیدگی">
    <form class="panel mb-5 grid gap-3 md:grid-cols-3" method="GET">
        <label class="grid gap-2 text-sm">پزشک<select class="input" name="doctor_id"><option value="">همه</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(request('doctor_id')==$doctor->id)>{{ $doctor->user->name }}</option>@endforeach</select></label>
        <label class="grid gap-2 text-sm">وضعیت<select class="input" name="status"><option value="">همه</option>@foreach(\App\Services\Appointments\ResolveScheduleConflict::STATUSES as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ __('planner.'.$status) }}</option>@endforeach</select></label>
        <label class="grid gap-2 text-sm">شناسه مسئول<input class="input" name="responsible_user_id" value="{{ request('responsible_user_id') }}" type="number"></label>
        <x-persian-date name="date" label="تاریخ نوبت" :value="request('date', '')"/>
        <button class="button self-end">فیلتر</button>
    </form>
    <div class="grid gap-4">@forelse($conflicts as $conflict)<a class="panel grid gap-2 border-amber-300 text-sm" href="{{ route('conflicts.show',$conflict->id) }}"><strong class="text-amber-700 dark:text-amber-300">⚠ نیازمند تغییر برنامه — {{ $conflict->first_name }} {{ $conflict->last_name }}</strong><span>{{ $conflict->mobile }} · {{ $conflict->doctor_name }} · {{ \App\Services\PersianDate::format(\Carbon\CarbonImmutable::parse($conflict->starts_at,'UTC'),true) }}</span><span>{{ $conflict->reason }} · {{ __('planner.'.$conflict->status) }} · {{ $conflict->responsible_name ?? 'بدون مسئول' }}</span></a>@empty<p class="panel">تعارض حل‌نشده‌ای وجود ندارد.</p>@endforelse</div><div class="mt-5">{{ $conflicts->links() }}</div>
</x-staff-page>
