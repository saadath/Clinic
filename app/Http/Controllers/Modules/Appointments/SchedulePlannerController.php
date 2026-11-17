<?php

namespace App\Http\Controllers\Modules\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Appointments\SchedulePlannerRequest;
use App\Models\Doctor;
use App\Services\Appointments\AppointmentAccess;
use App\Services\Appointments\PlannerCalendar;
use App\Services\Appointments\WeeklySchedulePlanner;
use App\Services\PersianDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchedulePlannerController extends Controller
{
    public function index(Request $request, AppointmentAccess $access): View
    {
        abort_unless($request->user()->can('schedules.manage'), 403);

        return view('appointments.planner', ['doctors' => $access->doctors($request->user())->with('user')->get(), 'today' => PersianDate::format(now())]);
    }

    public function preview(SchedulePlannerRequest $request, WeeklySchedulePlanner $planner): JsonResponse
    {
        return response()->json($planner->expand($request->validated()));
    }

    public function store(SchedulePlannerRequest $request, WeeklySchedulePlanner $planner): JsonResponse
    {
        return response()->json($planner->create($request->user(), $request->validated()));
    }

    public function calendar(Request $request, AppointmentAccess $access, PlannerCalendar $calendar): JsonResponse
    {
        $data = $request->validate(['doctor_id' => ['required', 'integer'], 'date' => ['required', 'string']]);
        $doctor = $access->schedule($request->user(), $data['doctor_id']);
        $month = $calendar->month($data['date']);
        $days = DB::table('doctor_schedule_days')->where('doctor_id', $doctor->id)->whereBetween('local_date', [$month['start']->toDateString(), $month['end']->toDateString()])->get()->keyBy('local_date');
        $conflicts = DB::table('appointment_schedule_conflicts')->join('appointments', 'appointments.id', '=', 'appointment_id')->where('doctor_id', $doctor->id)->whereNull('resolved_at')->pluck('appointments.starts_at')->map(fn ($date) => CarbonImmutable::parse($date, 'UTC')->setTimezone('Asia/Tehran')->toDateString())->all();
        $closures = DB::table('schedule_closures')->whereBetween('local_date', [$month['start']->toDateString(), $month['end']->toDateString()])->get();

        $calendarDays = [];
        for ($date = $month['start']; $date->lessThanOrEqualTo($month['end']); $date = $date->addDay()) {
            $day = $days->get($date->toDateString());
            $calendarDays[] = ['id' => $day?->id ?? $date->toDateString(), 'date' => $date->toDateString(), 'persian' => PersianDate::format($date),
                'weekday' => $date->dayOfWeek, 'revision' => $day?->revision, 'editable' => $day !== null,
                'base' => $day ? json_decode($day->base_json, true) : [], 'shifts' => $day ? json_decode($day->final_json, true) : [],
                'kind' => $day?->change_kind ?? 'unplanned', 'conflict' => in_array($date->toDateString(), $conflicts, true),
                'closed' => $closures->contains(fn ($closure) => $closure->local_date === $date->toDateString() && ($closure->doctor_id === null || (int) $closure->doctor_id === $doctor->id))];
        }

        return response()->json(['weeks' => $month['weeks'], 'padding' => $month['padding'], 'days' => $calendarDays]);
    }

    public function change(Request $request, AppointmentAccess $access, WeeklySchedulePlanner $planner): JsonResponse
    {
        $access->schedule($request->user(), (int) $request->input('doctor_id'));
        $revisions = $request->validate(['revisions' => ['sometimes', 'array'], 'revisions.*' => ['integer', 'min:1']]);
        $data = $request->validate(['doctor_id' => ['required', 'integer'], 'dates' => ['required', 'array', 'min:1', 'max:366'], 'dates.*' => ['required', 'string'], 'revision' => ['nullable', 'integer'], 'kind' => ['required', Rule::in(['closed', 'replace', 'add', 'remove'])], 'shift_key' => ['required_if:kind,remove', 'nullable', 'string'], 'reason' => ['required', 'string', 'max:500'], 'confirm_conflicts' => ['sometimes', 'boolean'], 'shifts' => ['required_unless:kind,closed,remove', 'array', 'max:6'], 'shifts.*.key' => ['required', 'regex:/^[a-zA-Z0-9_-]{1,50}$/D'], 'shifts.*.local_start' => ['required', 'date_format:H:i'], 'shifts.*.local_end' => ['required', 'date_format:H:i'], 'shifts.*.slot_minutes' => ['required', 'integer', 'between:5,240'], 'shifts.*.capacity' => ['required', 'integer', 'between:1,50'], 'shifts.*.break_start' => ['nullable', 'date_format:H:i'], 'shifts.*.break_end' => ['nullable', 'date_format:H:i']]);

        return response()->json($planner->changeDay($request->user(), $data + $revisions));
    }

    public function closure(Request $request, WeeklySchedulePlanner $planner, PlannerCalendar $calendar): JsonResponse
    {
        abort_unless($request->user()->can('doctors.manage'), 403);
        $data = $request->validate(['dates' => ['required', 'array', 'min:1', 'max:366'], 'dates.*' => ['required', 'string'], 'reason' => ['required', 'string', 'max:500'], 'kind' => ['required', Rule::in(['clinic', 'official'])], 'confirm_conflicts' => ['sometimes', 'boolean']]);

        return response()->json(DB::transaction(function () use ($request, $data, $planner, $calendar): array {
            $doctors = Doctor::orderBy('id')->lockForUpdate()->get();
            $affected = [];
            foreach (array_unique($data['dates']) as $input) {
                $date = $calendar->date($input)->toDateString();
                DB::table('schedule_closures')->insert(['local_date' => $date, 'kind' => $data['kind'], 'reason' => $data['reason'], 'created_by' => $request->user()->id, 'created_at' => now()]);
                $log = $planner->log($request->user(), null, $date, 'closure', null, $data, $data['reason']);
                foreach ($doctors as $doctor) {
                    $affected = [...$affected, ...$planner->detect($request->user(), $doctor, $date, $log, $data['reason'])];
                }
            }
            $planner->confirm($request->user(), $data, $affected);

            return ['conflicts' => count($affected)];
        }, 5));
    }
}
