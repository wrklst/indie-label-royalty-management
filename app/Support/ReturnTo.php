<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where a form returns to after saving: the page it was opened from,
 * passed as `return_to`. Only a path of this application is accepted, so
 * a crafted link cannot send the user to another site.
 */
class ReturnTo
{
    /**
     * The path with its query when it is a local path, otherwise null.
     */
    public static function path(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || strlen($value) > 2000) {
            return null;
        }

        // A single leading slash, no second slash or backslash right after it
        // (browsers read both as "//host"), no backslash, control character or space anywhere.
        if ($value[0] !== '/' || ($value[1] ?? '') === '/' || str_contains($value, '\\') || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }

        $parts = parse_url($value);

        return $parts === false || isset($parts['scheme']) || isset($parts['host']) ? null : $value;
    }

    /**
     * The path the form asked for, or the fallback.
     */
    public static function from(Request $request, string $fallback): string
    {
        return self::path($request->input('return_to')) ?? $fallback;
    }
}
