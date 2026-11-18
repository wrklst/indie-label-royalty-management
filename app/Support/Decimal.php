<?php

namespace App\Support;

/**
 * Arbitrary precision helpers for money and quantities.
 *
 * Distributor statements carry up to twelve decimals per line, so all
 * arithmetic goes through bcmath instead of floats.
 */
class Decimal
{
    public const int SCALE = 15;

    /**
     * Parse a number as written in a statement file into a bcmath string.
     *
     * Understands decimal commas ("0,000271889626"), thousands separators
     * ("1,234.56" and "1.234,56") and currency symbols. Returns null for
     * empty or non-numeric input.
     */
    public static function parse(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[^\d,.\-eE+]/u', '', trim((string) $value)) ?? '';

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $value = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif ($lastComma !== false) {
            $value = substr_count($value, ',') === 1 ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        }

        if (stripos($value, 'e') !== false) {
            if (! is_numeric($value)) {
                return null;
            }

            $value = number_format((float) $value, self::SCALE, '.', '');
        }

        if (! preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $value)) {
            return null;
        }

        return bcadd(ltrim($value, '+'), '0', self::SCALE);
    }

    public static function add(?string $left, ?string $right): string
    {
        return bcadd($left ?? '0', $right ?? '0', self::SCALE);
    }

    public static function sub(?string $left, ?string $right): string
    {
        return bcsub($left ?? '0', $right ?? '0', self::SCALE);
    }

    public static function mul(?string $left, ?string $right): string
    {
        return bcmul($left ?? '0', $right ?? '0', self::SCALE);
    }

    public static function div(?string $left, string $right): string
    {
        return bcdiv($left ?? '0', $right, self::SCALE);
    }

    public static function cmp(?string $left, ?string $right): int
    {
        return bccomp($left ?? '0', $right ?? '0', self::SCALE);
    }

    public static function isZero(?string $value): bool
    {
        return self::cmp($value, '0') === 0;
    }

    public static function isPositive(?string $value): bool
    {
        return self::cmp($value, '0') === 1;
    }

    public static function max(string $left, string $right): string
    {
        return self::cmp($left, $right) >= 0 ? $left : $right;
    }

    public static function min(string $left, string $right): string
    {
        return self::cmp($left, $right) <= 0 ? $left : $right;
    }

    /**
     * Round half away from zero to the given number of decimals.
     */
    public static function round(?string $value, int $decimals = 2): string
    {
        $value ??= '0';
        $offset = '0.'.str_repeat('0', $decimals).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, $decimals)
            : bcadd($value, $offset, $decimals);
    }
}
