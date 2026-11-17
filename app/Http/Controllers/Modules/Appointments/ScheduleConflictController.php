<?php

namespace App\Http\Controllers\Modules\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentAccess;
use App\Services\Appointments\PlannerCalendar;
use App\Services\Appointments\ResolveScheduleConflict;
use App\Services\Appointments\ScheduleAvailability;
use App\Services\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleConflictController extends Controller
{
    public function index(Request $request, AppointmentAccess $access, PlannerCalendar $calendar): View
    {
        abort_unless($request->user()->can('queue.manage'), 403);
        $filters = $request->validate(['doctor_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(ResolveScheduleConflict::STATUSES)], 'responsible_user_id' => ['nullable', 'integer'], 'date' => ['nullable', 'string']]);
        if (! empty($filters['date'])) {
            $filters['date'] = $calendar->date($filters['date'])->toDateString();
        }
        $conflicts = DB::table('appointment_schedule_conflicts as c')->join('appointments as a', 'a.id', '=', 'c.appointment_id')->join('patients as p', 'p.id', '=', 'a.patient_id')->join('doctors as d', 'd.id', '=', 'a.doctor_id')->join('users as u', 'u.id', '=', 'd.user_id')->leftJoin('users as staff', 'staff.id', '=', 'c.responsible_user_id')
            ->whereIn('a.doctor_id', $access->doctors($request->user())->select('doctors.id'))->whereNull('c.resolved_at')
            ->when($filters['doctor_id'] ?? null, fn ($q, $v) => $q->where('a.doctor_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('c.status', $v))->when($filters['responsible_user_id'] ?? null, fn ($q, $v) => $q->where('c.responsible_user_id', $v))
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->where('a.starts_at', '>=', CarbonImmutable::parse($v, 'Asia/Tehran')->utc())->where('a.starts_at', '<', CarbonImmutable::parse($v, 'Asia/Tehran')->addDay()->utc()))
            ->select('c.*', 'p.first_name', 'p.last_name', 'p.mobile', 'u.name as doctor_name', 'a.starts_at', 'staff.name as responsible_name')->orderBy('c.id')->paginate(20)->withQueryString();

        return view('appointments.conflicts', ['conflicts' => $conflicts, 'doctors' => $access->doctors($request->user())->with('user')->get()]);
    }

    public function show(Request $request, int $conflict, AppointmentAccess $access, ScheduleAvailability $availability, PlannerCalendar $calendar): View
    {
        $item = DB::table('appointment_schedule_conflicts')->find($conflict);
        abort_unless($item, 404);
        $appointment = Appointment::with('patient', 'doctor.user')->findOrFail($item->appointment_id);
        $access->authorize($request->user(), $appointment, 'queue.manage');
        $date = $request->validate(['date' => ['nullable', 'string']])['date'] ?? PersianDate::format(now());
        $slots = $availability->slots($appointment->doctor, $calendar->date($date), $appointment->id);

        return view('appointments.conflict', ['conflict' => $item, 'appointment' => $appointment, 'slots' => $slots, 'date' => $date, 'proposals' => DB::table('appointment_reschedule_requests')->where('conflict_id', $conflict)->get(), 'notifications' => DB::table('notification_logs')->where('conflict_id', $conflict)->get()]);
    }

    public function update(Request $request, int $conflict, ResolveScheduleConflict $resolver): RedirectResponse
    {
        if ($request->filled('proposed_start')) {
            $request->merge(['proposed_start' => PersianDate::parse($request->input('proposed_start'), true) ?? 'invalid']);
        }
        $data = $request->validate(['revision' => ['required', 'integer'], 'status' => ['required', Rule::in(ResolveScheduleConflict::STATUSES)], 'proposed_start' => ['required_if:status,time_proposed', 'nullable', 'date_format:Y-m-d\TH:i'], 'proposal_id' => ['required_if:status,patient_confirmed', 'nullable', 'integer'], 'patient_response' => ['nullable', 'string', 'max:2000']]);
        $resolver->update($request->user(), $conflict, $data);

        return back()->with('status', 'رسیدگی ثبت شد.');
    }

    public function notify(Request $request, int $conflict, AppointmentAccess $access): RedirectResponse
    {
        $item = DB::table('appointment_schedule_conflicts')->find($conflict);
        abort_unless($item, 404);
        $appointment = Appointment::findOrFail($item->appointment_id);
        $access->authorize($request->user(), $appointment, 'queue.manage');
        $data = $request->validate(['channel' => ['required', Rule::in(['phone', 'sms', 'messenger', 'patient_panel'])], 'result' => ['required', 'string', 'max:2000'], 'delivery' => ['required', Rule::in(['contacted', 'failed', 'no_answer'])]]);
        $message = 'بیمار گرامی، برنامه حضور پزشک در تاریخ رزرو شما تغییر کرده است. برای هماهنگی زمان جدید، لطفاً با کلینیک تماس بگیرید. این پیام به معنی لغو نوبت نیست.';
        DB::table('notification_logs')->insert(['conflict_id' => $conflict, 'channel' => $data['channel'], 'status' => $data['channel'] === 'phone' ? $data['delivery'] : 'not_configured', 'message' => $message, 'result' => $data['channel'] === 'phone' ? $data['result'] : 'کانال ارسال متصل نیست؛ ارسال انجام نشد. '.$data['result'], 'actor_id' => $request->user()->id, 'created_at' => now()]);

        return back()->with('status', 'نتیجه اطلاع‌رسانی ثبت شد؛ کانال‌های اتصال‌نیافته پیام ارسال نمی‌کنند.');
    }
}
