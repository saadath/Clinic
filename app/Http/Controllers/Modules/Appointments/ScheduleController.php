<?php

namespace App\Http\Controllers\Modules\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorScheduleRule;
use App\Models\ScheduleException;
use App\Services\Appointments\AppointmentAccess;
use App\Services\Appointments\ScheduleAvailability;
use App\Services\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function __construct(private AppointmentAccess $access) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('schedules.manage'), 403);
        $doctors = $this->access->doctors($request->user())->when(! $request->user()->can('doctors.manage'), fn ($query) => $query->where('user_id', $request->user()->id))->with('user')->get();
        $data = $request->validate(['doctor_id' => ['nullable', 'integer']]);
        $doctor = isset($data['doctor_id']) ? $doctors->firstWhere('id', $data['doctor_id']) : $doctors->first();
        abort_if(isset($data['doctor_id']) && ! $doctor, 404);
        $rules = $doctor ? DoctorScheduleRule::where('doctor_id', $doctor->id)->orderBy('weekday')->orderBy('local_start')->get() : collect();
        $exceptions = $doctor ? ScheduleException::where('doctor_id', $doctor->id)->orderByDesc('local_date')->get() : collect();

        return view('appointments.schedules', compact('doctors', 'doctor', 'rules', 'exceptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->schedule($request->user(), (int) $request->input('doctor_id'));
        if ($request->input('date_calendar') === 'persian') {
            foreach (['effective_from', 'effective_to', 'local_date'] as $field) {
                if ($request->filled($field) && is_string($request->input($field))) {
                    $request->merge([$field => PersianDate::parse($request->input($field)) ?? 'invalid']);
                }
            }
        }
        $exception = $request->input('type') === 'exception';
        $data = $request->validate($exception ? [
            'doctor_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(['closed', 'break'])], 'local_date' => ['required', 'date_format:Y-m-d'],
            'local_start' => ['required_if:kind,break', 'nullable', 'date_format:H:i'], 'local_end' => ['required_if:kind,break', 'nullable', 'date_format:H:i', 'after:local_start'], 'reason' => ['nullable', 'string', 'max:500'],
        ] : [
            'doctor_id' => ['required', 'integer'], 'weekday' => ['required', 'integer', 'between:0,6'], 'local_start' => ['required', 'date_format:H:i'], 'local_end' => ['required', 'date_format:H:i', 'after:local_start'],
            'effective_from' => ['required', 'date_format:Y-m-d'], 'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'], 'slot_minutes' => ['required', 'integer', 'between:5,240'], 'capacity' => ['required', 'integer', 'between:1,50'],
        ]);
        DB::transaction(function () use ($request, $data, $exception): void {
            Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            $this->access->schedule($request->user(), $data['doctor_id']);
            if ($exception) {
                $start = CarbonImmutable::parse($data['local_date'].' '.($data['kind'] === 'closed' ? '00:00' : $data['local_start']), 'Asia/Tehran');
                $end = $data['kind'] === 'closed' ? $start->addDay() : CarbonImmutable::parse($data['local_date'].' '.$data['local_end'], 'Asia/Tehran');
                if (Appointment::where('doctor_id', $data['doctor_id'])->whereIn('status', ScheduleAvailability::ACTIVE)->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->exists()) {
                    throw ValidationException::withMessages(['local_date' => __('appointments.existing_bookings')]);
                }
                ScheduleException::create($data + ['created_by' => $request->user()->id]);
            } else {
                $overlap = DoctorScheduleRule::where('doctor_id', $data['doctor_id'])->where('weekday', $data['weekday'])->where('local_start', '<', $data['local_end'])->where('local_end', '>', $data['local_start'])
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))
                    ->when($data['effective_to'] ?? null, fn ($query, $until) => $query->where('effective_from', '<=', $until))->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['local_start' => __('appointments.schedule_overlap')]);
                }
                DoctorScheduleRule::create($data + ['created_by' => $request->user()->id]);
            }
        }, 5);

        return back()->with('status', __('appointments.saved'));
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['rule', 'exception'], true), 404);
        $model = $type === 'rule' ? DoctorScheduleRule::findOrFail($id) : ScheduleException::findOrFail($id);
        $this->access->schedule($request->user(), $model->doctor_id);
        DB::transaction(function () use ($request, $model, $type): void {
            Doctor::whereKey($model->doctor_id)->lockForUpdate()->firstOrFail();
            $this->access->schedule($request->user(), $model->doctor_id);
            if ($type === 'rule' && Appointment::where('doctor_id', $model->doctor_id)->whereIn('status', ['scheduled', 'waiting', 'in_progress'])->where('ends_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['schedule' => __('appointments.existing_bookings')]);
            }
            $model->delete();
        }, 5);

        return back()->with('status', __('appointments.saved'));
    }
}
