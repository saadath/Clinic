<?php

namespace App\Http\Controllers\Modules\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Appointments\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\Appointments\AppointmentAccess;
use App\Services\Appointments\ManageAppointment;
use App\Services\Appointments\ScheduleAvailability;
use App\Services\Patients\PatientInput;
use App\Services\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentAccess $access) {}

    public function index(Request $request, ScheduleAvailability $availability): View
    {
        Gate::authorize('appointments.view');
        $filters = $request->validate(['doctor_id' => ['nullable', 'integer'], 'date' => ['nullable', 'string', 'max:10'], 'view' => ['nullable', Rule::in(['day', 'week', 'month'])], 'q' => ['nullable', 'string', 'max:100']]);
        $dateInput = $filters['date'] ?? PersianDate::format(now());
        $parsed = PersianDate::parse($dateInput);
        abort_unless($parsed, 422, __('appointments.invalid_date'));
        $date = CarbonImmutable::parse($parsed, 'Asia/Tehran');
        $mode = $filters['view'] ?? 'day';
        $doctors = $this->access->doctors($request->user())->with('user:id,name')->get();
        $doctor = isset($filters['doctor_id']) ? $doctors->firstWhere('id', (int) $filters['doctor_id']) : $doctors->first();
        abort_if(isset($filters['doctor_id']) && ! $doctor, 404);
        $start = $mode === 'week' ? $date->startOfWeek(6) : $date;
        $daysCount = $mode === 'week' ? 7 : 1;
        if ($mode === 'month') {
            $persian = explode('/', PersianDate::format($date));
            $start = CarbonImmutable::parse(PersianDate::parse($persian[0].'/'.$persian[1].'/01'), 'Asia/Tehran');
            $nextYear = (int) $persian[0] + ((int) $persian[1] === 12 ? 1 : 0);
            $nextMonth = (int) $persian[1] === 12 ? 1 : (int) $persian[1] + 1;
            $next = CarbonImmutable::parse(PersianDate::parse(sprintf('%04d/%02d/01', $nextYear, $nextMonth)), 'Asia/Tehran');
            $daysCount = (int) $start->diffInDays($next);
        }
        $end = $start->addDays($daysCount);
        $appointments = $doctor ? $this->access->query($request->user())->where('doctor_id', $doctor->id)->where('starts_at', '>=', $start->utc())->where('starts_at', '<', $end->utc())->with('patient')->orderBy('starts_at')->orderBy('id')->get() : collect();
        $days = collect(range(0, $daysCount - 1))->map(fn (int $offset) => $start->addDays($offset));
        $grouped = $appointments->groupBy(fn (Appointment $item) => $item->starts_at->setTimezone('Asia/Tehran')->toDateString());
        $slots = $doctor && $mode === 'day' ? $availability->slots($doctor, $date) : [];
        $patients = $request->user()->can('appointments.manage') && ! empty($filters['q']) ? Patient::visibleTo($request->user())->where(fn ($query) => $query->where('national_id', PatientInput::digits($filters['q']))->orWhere('mobile', PatientInput::digits($filters['q']))->orWhere('last_name', 'like', '%'.$filters['q'].'%'))->orderBy('id')->limit(20)->get() : collect();
        $queue = $doctor ? DB::table('queue_entries')->where('doctor_id', $doctor->id)->where('local_date', $date->toDateString())->get()->keyBy('appointment_id') : collect();
        $waiting = $appointments->whereIn('status', ['waiting', 'in_progress'])->sortBy(fn (Appointment $item) => $queue->get($item->id)?->queue_number ?? PHP_INT_MAX);

        return view('appointments.index', compact('doctor', 'doctors', 'date', 'dateInput', 'mode', 'days', 'grouped', 'slots', 'patients', 'queue', 'waiting'));
    }

    public function store(AppointmentRequest $request, ManageAppointment $manage): RedirectResponse
    {
        $appointment = $manage->save($request->user(), $request->validated());

        return redirect()->route('appointments.show', $appointment)->with('status', __('appointments.saved'));
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->access->authorize($request->user(), $appointment, 'appointments.view');
        $appointment->load('doctor.user', 'patient');
        $events = DB::table('appointment_status_events')->join('users', 'users.id', '=', 'actor_id')->where('appointment_id', $appointment->id)->select('appointment_status_events.*', 'users.name as actor_name')->orderBy('appointment_status_events.id')->get();

        return view('appointments.show', compact('appointment', 'events'));
    }

    public function update(AppointmentRequest $request, Appointment $appointment, ManageAppointment $manage): RedirectResponse
    {
        $manage->save($request->user(), $request->validated(), $appointment);

        return back()->with('status', __('appointments.saved'));
    }

    public function status(Request $request, Appointment $appointment, ManageAppointment $manage): RedirectResponse
    {
        Gate::authorize('queue.manage');
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ManageAppointment::TRANSITIONS))], 'revision' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:500']]);
        $manage->transition($request->user(), $appointment, $data['status'], $data['revision'], $data['reason'] ?? null);

        return back()->with('status', __('appointments.saved'));
    }
}
