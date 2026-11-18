<?php

namespace App\Services\Imports;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * One line of a statement file with typed access to its columns.
 *
 * Columns are looked up without regard to capitalisation, underscores or
 * extra spaces, and through the aliases the importer declares. Every
 * getter takes several column names and uses the first one that has a
 * value. The "require" getters reject the line with a readable message.
 */
class Row
{
    /**
     * @param  array<string, string>  $raw  The line as read, keyed by the file's own headers.
     * @param  array<string, string>  $index  Normalised column name => header in the file.
     */
    public function __construct(
        public readonly int $line,
        public readonly array $raw,
        private readonly array $index,
    ) {}

    /**
     * "Paid_Date " and "paid  date" both become "paid date".
     */
    public static function normalize(string $column): string
    {
        return mb_strtolower(trim(preg_replace('/[\s_]+/u', ' ', $column) ?? $column));
    }

    /**
     * Whether the file has the column at all, with or without a value.
     */
    public function has(string $column): bool
    {
        return isset($this->index[self::normalize($column)]);
    }

    public function string(string ...$columns): ?string
    {
        foreach ($columns as $column) {
            $header = $this->index[self::normalize($column)] ?? null;
            $value = $header === null ? '' : ($this->raw[$header] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function requireString(string $column): string
    {
        return $this->string($column) ?? throw new RowException("\"{$column}\" is empty.");
    }

    public function lower(string ...$columns): ?string
    {
        $value = $this->string(...$columns);

        return $value === null ? null : mb_strtolower($value);
    }

    public function upper(string ...$columns): ?string
    {
        $value = $this->string(...$columns);

        return $value === null ? null : mb_strtoupper($value);
    }

    /**
     * A number as a bcmath string. Understands decimal commas, thousands
     * separators and currency symbols.
     */
    public function decimal(string ...$columns): ?string
    {
        foreach ($columns as $column) {
            $value = Decimal::parse($this->string($column));

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function requireDecimal(string $column): string
    {
        return $this->decimal($column) ?? throw new RowException("\"{$column}\" is not a number.");
    }

    public function integer(string ...$columns): ?int
    {
        $value = $this->string(...$columns);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * A date as "2026-03-31". Pass the PHP date format the source uses
     * when it is ambiguous (e.g. "d/m/Y"); otherwise the value is parsed
     * freely. An unreadable date rejects the line.
     */
    public function date(string $column, ?string $format = null): ?string
    {
        $value = $this->string($column);

        if ($value === null) {
            return null;
        }

        if ($format !== null) {
            try {
                return CarbonImmutable::createFromFormat($format, $value)->toDateString();
            } catch (Throwable) {
                // Fall through to free parsing.
            }
        }

        try {
            // Read freely, "31.03.26" is a time of day and a value without a year is today.
            if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2}$/', $value) === 1) {
                return CarbonImmutable::createFromFormat('!d.m.y', $value)->toDateString();
            }

            // "03/04/2026" is 3 April in Europe and 4 March in the US: not guessed without a format.
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $value) === 1) {
                throw new RowException("The date \"{$value}\" in \"{$column}\" can be read two ways. Use the format 2026-03-31.");
            }

            if (preg_match('/\d{4}/', $value) !== 1 || preg_match('/^(\d{1,2}[.\/-])?\d{4}$/', $value) === 1) {
                throw new RowException("The date \"{$value}\" in \"{$column}\" could not be read. Use the format 2026-03-31.");
            }

            return CarbonImmutable::parse($value)->toDateString();
        } catch (RowException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RowException("The date \"{$value}\" in \"{$column}\" could not be read. Use the format 2026-03-31.");
        }
    }

    public function requireDate(string $column, ?string $format = null): string
    {
        return $this->date($column, $format) ?? throw new RowException("\"{$column}\" is empty.");
    }

    /**
     * The last day of a reporting month written as "2026-02".
     */
    public function monthEnd(string $column): ?string
    {
        $value = $this->string($column);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^(\d{4})-(\d{1,2})(?:-\d{1,2})?\b/', $value, $match) || (int) $match[2] < 1 || (int) $match[2] > 12) {
            throw new RowException("\"{$column}\" must look like 2026-02.");
        }

        return CarbonImmutable::create((int) $match[1], (int) $match[2], 1)->endOfMonth()->toDateString();
    }

    public function requireMonthEnd(string $column): string
    {
        return $this->monthEnd($column) ?? throw new RowException("\"{$column}\" must look like 2026-02.");
    }

    public function isrc(string ...$columns): ?string
    {
        return Identifiers::isrc($this->string(...$columns));
    }

    public function upc(string ...$columns): ?string
    {
        return Identifiers::upc($this->string(...$columns));
    }

    /**
     * An ISO 3166-1 alpha-2 country code, or null for placeholders.
     */
    public function region(string ...$columns): ?string
    {
        return Identifiers::region($this->string(...$columns));
    }

    /**
     * The line for storage without the given columns, e.g. personal data
     * of buyers. Keys are lower-cased.
     *
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public function rawWithout(array $columns): array
    {
        $drop = array_flip(array_map(self::normalize(...), $columns));
        $kept = [];

        foreach ($this->raw as $header => $value) {
            if (! isset($drop[self::normalize((string) $header)])) {
                $kept[mb_strtolower((string) $header)] = $value;
            }
        }

        return $kept;
    }
}
