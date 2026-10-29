<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks the format and check digit of a Finnish business ID (Y-tunnus), e.g. 0112038-9.
 */
class FinnishBusinessId implements ValidationRule
{
    private const array WEIGHTS = [7, 9, 10, 5, 8, 4, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{7})-(\d)$/', $value, $parts)) {
            $fail('The :attribute must look like 1234567-8.');

            return;
        }

        if ($this->checkDigit($parts[1]) !== (int) $parts[2]) {
            $fail('The :attribute is not a valid Finnish business ID.');
        }
    }

    private function checkDigit(string $digits): ?int
    {
        $sum = 0;

        foreach (str_split($digits) as $index => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$index];
        }

        $remainder = $sum % 11;

        return match ($remainder) {
            0 => 0,
            1 => null,
            default => 11 - $remainder,
        };
    }
}
