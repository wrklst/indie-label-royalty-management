<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reads a text filter from the query string. `?search[]=x` is no text, so
 * it filters nothing instead of ending in an error.
 */
final class Filters
{
    public static function text(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }
}
