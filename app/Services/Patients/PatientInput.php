<?php

namespace App\Services\Patients;

class PatientInput
{
    public static function digits(string $value): string
    {
        return strtr($value, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    }
}
