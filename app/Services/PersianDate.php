<?php

namespace App\Services;

use App\Services\Patients\PatientInput;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use IntlTimeZone;

class PersianDate
{
    public static function format(?DateTimeInterface $date, bool $withTime = false): string
    {
        if ($date === null) {
            return '';
        }
        $formatter = self::formatter($withTime);

        return PatientInput::digits($formatter->format($date));
    }

    public static function parse(string $value, bool $withTime = false): ?string
    {
        $value = PatientInput::digits(trim($value));
        $pattern = $withTime ? '/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}$/D' : '/^\d{4}\/\d{2}\/\d{2}$/D';
        if (! preg_match($pattern, $value)) {
            return null;
        }
        $formatter = self::formatter($withTime);
        $formatter->setLenient(false);
        $timestamp = $formatter->parse($value);
        if ($timestamp === false || PatientInput::digits($formatter->format($timestamp)) !== $value) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($timestamp, 'Asia/Tehran')->format($withTime ? 'Y-m-d\TH:i' : 'Y-m-d');
    }

    private static function formatter(bool $withTime): IntlDateFormatter
    {
        return new IntlDateFormatter('en_US@calendar=persian', IntlDateFormatter::NONE, IntlDateFormatter::NONE, IntlTimeZone::createTimeZone('Asia/Tehran'), IntlDateFormatter::TRADITIONAL, $withTime ? 'yyyy/MM/dd HH:mm' : 'yyyy/MM/dd');
    }
}
