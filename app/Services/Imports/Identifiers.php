<?php

namespace App\Services\Imports;

/**
 * Normalises catalog identifiers so files from different sources match.
 */
class Identifiers
{
    /**
     * "DE-A1B-26-00001" and "dea1b2600001" both become "DEA1B2600001".
     */
    public static function isrc(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '');

        return strlen($value) === 12 ? $value : null;
    }

    /**
     * Strip spaces and dashes from a UPC or EAN.
     */
    public static function upc(?string $value): ?string
    {
        $value = preg_replace('/\D/', '', (string) $value) ?? '';

        return strlen($value) >= 8 ? $value : null;
    }

    /**
     * A key that treats a 12 digit UPC and its 13 digit EAN form as equal.
     */
    public static function upcKey(?string $value): ?string
    {
        $value = self::upc($value);

        return $value === null ? null : ltrim($value, '0');
    }

    public static function catalogKey(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function nameKey(?string $value): ?string
    {
        $value = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Reduce a country value to an ISO 3166-1 alpha-2 code, or null for
     * placeholders such as Zebralution's "XX".
     */
    public static function region(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) && $value !== 'XX' ? $value : null;
    }
}
