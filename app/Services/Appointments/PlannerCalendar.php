<?php

namespace App\Services\Appointments;

use App\Services\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PlannerCalendar
{
    public function date(string $value): CarbonImmutable
    {
        $parsed = PersianDate::parse($value);
        if (! $parsed) {
            throw ValidationException::withMessages(['date' => 'تاریخ شمسی معتبر نیست.']);
        }

        return CarbonImmutable::parse($parsed, 'Asia/Tehran');
    }

    public function addMonths(CarbonImmutable $start, int $months): CarbonImmutable
    {
        [$year, $month, $day] = array_map('intval', explode('/', PersianDate::format($start)));
        $index = $year * 12 + $month - 1 + $months;
        for ($candidate = $day; $candidate >= 1; $candidate--) {
            $parsed = PersianDate::parse(sprintf('%04d/%02d/%02d', intdiv($index, 12), $index % 12 + 1, $candidate));
            if ($parsed) {
                return CarbonImmutable::parse($parsed, 'Asia/Tehran');
            }
        }
        throw ValidationException::withMessages(['date' => 'بازه تاریخ معتبر نیست.']);
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, weeks: array<int, array<string, string>>} */
    public function range(array $data): array
    {
        $start = $this->date($data['starts_on']);
        $end = match ($data['period']) {
            'week' => $start->addDays(6),
            'two_weeks' => $start->addDays(13),
            'custom' => $this->date($data['ends_on']),
            default => $this->addMonths($start, (int) $data['months'])->subDay(),
        };
        if (! empty($data['valid_until'])) {
            $limit = $this->date($data['valid_until']);
            $end = $end->min($limit);
        }
        if ($end->lessThan($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['ends_on' => 'بازه باید پس از شروع و حداکثر یک سال باشد.']);
        }
        $weeks = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $weekEnd->addDay()) {
            $weekEnd = $cursor->endOfWeek(5)->startOfDay()->min($end);
            $weeks[] = ['start' => $cursor->toDateString(), 'end' => $weekEnd->toDateString(), 'label' => PersianDate::format($cursor).' تا '.PersianDate::format($weekEnd)];
        }

        return compact('start', 'end', 'weeks');
    }

    /** @return array<string, mixed> */
    public function month(string $date): array
    {
        $selected = $this->date($date);
        $parts = explode('/', PersianDate::format($selected));
        $start = $this->date($parts[0].'/'.$parts[1].'/01');
        $end = $this->addMonths($start, 1)->subDay();
        $weeks = [];
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $ordinals = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم'];
        for ($cursor = $start->startOfWeek(6); $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addWeek()) {
            $weeks[] = ['start' => $cursor->toDateString(), 'end' => $cursor->addDays(6)->toDateString(), 'label' => 'هفته '.$ordinals[count($weeks)].' '.$months[(int) $parts[1] - 1].' '.$parts[0].' — '.PersianDate::format($cursor).' تا '.PersianDate::format($cursor->addDays(6))];
        }

        return ['start' => $start, 'end' => $end, 'weeks' => $weeks, 'padding' => ($start->dayOfWeek + 1) % 7];
    }
}
