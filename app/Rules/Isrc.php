<?php

namespace App\Rules;

use App\Services\Imports\Identifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Isrc implements ValidationRule
{
    /**
     * An ISRC has twelve letters and digits; dashes and spaces are ignored.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || Identifiers::isrc($value) === null) {
            $fail('The ISRC must have 12 letters and digits, for example DE-A1B-26-00001.');
        }
    }
}
