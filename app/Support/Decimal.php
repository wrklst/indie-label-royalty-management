<?php

namespace App\Support;

use ValueError;

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
     *
     * @return numeric-string|null
     */
    public static function parse(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        // Accounting layouts write a negative as "(12.34)", typeset ones with a real minus sign.
        $isNegative = preg_match('/^\(.*\)$|^[−–]/u', $text) === 1;

        $value = preg_replace('/[^\d,.\-eE+]/u', '', $text) ?? '';
        // An E is an exponent only between digits; elsewhere it is left over from a currency code.
        $value = preg_replace('/(?<!\d)[eE]|[eE](?![\d+\-])/', '', $value) ?? '';

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        if ($isNegative && ! str_starts_with($value, '-')) {
            $value = '-'.ltrim($value, '+');
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

        return bcadd(self::operand(ltrim($value, '+')), '0', self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function add(?string $left, ?string $right): string
    {
        return bcadd(self::operand($left), self::operand($right), self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function sub(?string $left, ?string $right): string
    {
        return bcsub(self::operand($left), self::operand($right), self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function mul(?string $left, ?string $right): string
    {
        return bcmul(self::operand($left), self::operand($right), self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function div(?string $left, string $right): string
    {
        return bcdiv(self::operand($left), self::operand($right), self::SCALE);
    }

    public static function cmp(?string $left, ?string $right): int
    {
        return bccomp(self::operand($left), self::operand($right), self::SCALE);
    }

    public static function isZero(?string $value): bool
    {
        return self::cmp($value, '0') === 0;
    }

    public static function isPositive(?string $value): bool
    {
        return self::cmp($value, '0') === 1;
    }

    /**
     * @return numeric-string
     */
    public static function abs(?string $value): string
    {
        return self::cmp($value, '0') < 0 ? self::sub('0', $value) : self::add($value, '0');
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
     *
     * @return numeric-string
     */
    public static function round(?string $value, int $decimals = 2): string
    {
        $value = self::operand($value);
        $offset = self::operand('0.'.str_repeat('0', $decimals).'5');

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, $decimals)
            : bcadd($value, $offset, $decimals);
    }

    /**
     * Turn an operand into the numeric string bcmath expects.
     *
     * Null counts as zero, and so does a string holding only a sign or a
     * decimal point, which is how bcmath itself reads those. Anything else
     * that is not a number is rejected the way bcmath rejects it.
     *
     * @return numeric-string
     *
     * @throws ValueError
     */
    private static function operand(?string $value): string
    {
        if ($value === null) {
            return '0';
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (preg_match('/^[+-]?\.?$/D', $value) === 1) {
            return '0';
        }

        throw new ValueError('Decimal operand must be a well-formed number.');
    }
}
