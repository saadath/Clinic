<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{10}$/D', $value) || preg_match('/^(\d)\1{9}$/D', $value)) {
            $fail(__('patients.national_invalid'));

            return;
        }
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += (int) $value[$index] * (10 - $index);
        }
        $remainder = $sum % 11;
        if ((int) $value[9] !== ($remainder < 2 ? $remainder : 11 - $remainder)) {
            $fail(__('patients.national_invalid'));
        }
    }
}
