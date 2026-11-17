<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorScheduleRule;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;

class ScheduleAvailability
{
    public const ACTIVE = ['scheduled', 'waiting', 'in_progress', 'completed'];

    /** @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, capacity: int, booked: int}> */
    public function slots(Doctor $doctor, CarbonImmutable $date, ?int $exceptId = null): array
    {
        $date = $date->setTimezone('Asia/Tehran')->startOfDay();
        $localDate = $date->toDateString();
        $exceptions = ScheduleException::where('doctor_id', $doctor->id)->where('local_date', $localDate)->get();
        if ($exceptions->contains('kind', 'closed')) {
            return [];
        }
        $rules = DoctorScheduleRule::where('doctor_id', $doctor->id)->where('weekday', $date->dayOfWeek)
            ->where('effective_from', '<=', $localDate)->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $localDate))->orderBy('local_start')->get();
        $bookings = Appointment::where('doctor_id', $doctor->id)->whereIn('status', self::ACTIVE)
            ->where('starts_at', '<', $date->addDay()->utc())->where('ends_at', '>', $date->utc())
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->get();
        $slots = [];
        foreach ($rules as $rule) {
            $cursor = CarbonImmutable::parse($localDate.' '.$rule->local_start, 'Asia/Tehran');
            $end = CarbonImmutable::parse($localDate.' '.$rule->local_end, 'Asia/Tehran');
            while ($cursor->addMinutes($rule->slot_minutes)->lessThanOrEqualTo($end)) {
                $slotEnd = $cursor->addMinutes($rule->slot_minutes);
                $blocked = $exceptions->contains(fn ($exception) => $exception->kind === 'break'
                    && $cursor->lessThan(CarbonImmutable::parse($localDate.' '.$exception->local_end, 'Asia/Tehran'))
                    && $slotEnd->greaterThan(CarbonImmutable::parse($localDate.' '.$exception->local_start, 'Asia/Tehran')));
                if (! $blocked) {
                    $slots[] = ['start' => $cursor, 'end' => $slotEnd, 'capacity' => $rule->capacity,
                        'booked' => $bookings->filter(fn ($appointment) => $appointment->starts_at->lessThan($slotEnd) && $appointment->ends_at->greaterThan($cursor))->count()];
                }
                $cursor = $slotEnd;
            }
        }

        return $slots;
    }
}
