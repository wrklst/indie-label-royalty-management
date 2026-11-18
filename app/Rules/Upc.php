<?php

namespace App\Rules;

use App\Services\Imports\Identifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Upc implements ValidationRule
{
    /**
     * A UPC or EAN has 8 to 14 digits; dashes and spaces are ignored.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = is_string($value) || is_int($value) ? Identifiers::upc((string) $value) : null;

        if ($digits === null || strlen($digits) > 14 || preg_match('/[^\d\s-]/', (string) $value)) {
            $fail('The UPC / EAN must have 8 to 14 digits.');
        }
    }
}
