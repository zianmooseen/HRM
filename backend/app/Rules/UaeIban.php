<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UaeIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute must be a valid UAE IBAN.');
        }
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtoupper((string) preg_replace('/\s+/', '', $value));
    }

    public static function isValid(?string $value): bool
    {
        $iban = self::normalize($value);

        if ($iban === null || preg_match('/^AE\d{21}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';

        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
        }

        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = (($remainder * 10) + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
