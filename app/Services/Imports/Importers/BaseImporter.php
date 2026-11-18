<?php

namespace App\Services\Imports\Importers;

use App\Models\Label;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRow;
use App\Services\Imports\ImportRowError;
use App\Services\Imports\Readers\SheetReader;
use App\Services\Imports\Row;
use App\Services\Imports\RowException;
use Generator;

/**
 * Base class for importers. A new importer is one class in this
 * directory; it is picked up automatically. Create one with
 * `php artisan make:importer`.
 *
 * Declare key(), sourceName(), description() and requiredColumns(), then
 * turn each line into an ImportRow in map(). The same class reads CSV and
 * Excel files: encoding, delimiter, title lines above the header and
 * column capitalisation are handled before map() is called.
 *
 * For a new layout of an existing source, add a second class that returns
 * the same source() and a version(). The layout of an uploaded file is
 * detected from requiredColumns(), so list columns that tell the layouts
 * apart. Extend the existing importer when only a few columns changed.
 */
abstract class BaseImporter implements Importer
{
    /** The label being imported into, available in map(). */
    protected Label $label;

    /** @var array<string, mixed> Options chosen for this import, available in map(). */
    protected array $options = [];

    /**
     * Turn one line into a transaction.
     *
     * Call skip() for lines that are not transactions (subtotals, payouts)
     * and fail() for lines that should be reported as errors. The "require"
     * getters of the row fail the line for you.
     */
    abstract protected function map(Row $row): ImportRow;

    public function source(): string
    {
        return $this->key();
    }

    public function version(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return $this->version() === null ? $this->sourceName() : "{$this->sourceName()} ({$this->version()})";
    }

    public function requiresPaidDate(): bool
    {
        return false;
    }

    public function priority(): int
    {
        return 0;
    }

    /**
     * Other headers the source uses for a column: alias => column.
     *
     * @return array<string, string>
     */
    protected function aliases(): array
    {
        return [];
    }

    /**
     * Runs once per file before the first line, with $label and $options set.
     */
    protected function prepare(): void {}

    public function matches(SheetReader $reader): bool
    {
        return $reader->locateHeader(fn (array $headers): bool => $this->accepts($headers));
    }

    public function rows(SheetReader $reader, Label $label, array $options = []): Generator
    {
        if (! $this->matches($reader)) {
            throw new ImportException(sprintf(
                'This is not a %s file: the columns "%s" are required.',
                $this->name(),
                implode('", "', $this->requiredColumns()),
            ));
        }

        $this->label = $label;
        $this->options = $options;
        $this->prepare();

        $index = $this->index($reader->headers());

        foreach ($reader->rows() as $line => $values) {
            try {
                yield $line => $this->map(new Row($line, $values, $index));
            } catch (RowException $exception) {
                yield $line => new ImportRowError($exception->getMessage(), $exception->skipped);
            }
        }
    }

    /**
     * Leave the line out without reporting it as a problem.
     */
    protected function skip(string $reason): never
    {
        throw new RowException($reason, skipped: true);
    }

    /**
     * Leave the line out and list it under the import's errors.
     */
    protected function fail(string $reason): never
    {
        throw new RowException($reason);
    }

    /**
     * @param  list<string>  $headers
     */
    private function accepts(array $headers): bool
    {
        $index = $this->index($headers);

        return array_all($this->requiredColumns(), fn (string $column): bool => isset($index[Row::normalize($column)]));
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string> Normalised column name => header in the file.
     */
    private function index(array $headers): array
    {
        $aliases = [];

        foreach ($this->aliases() as $alias => $column) {
            $aliases[Row::normalize($alias)] = Row::normalize($column);
        }

        $index = [];

        foreach ($headers as $header) {
            $normalized = Row::normalize($header);
            $index[$normalized] ??= $header;
        }

        // A real column always wins over an alias pointing at the same name.
        foreach ($headers as $header) {
            $normalized = Row::normalize($header);

            if (isset($aliases[$normalized])) {
                $index[$aliases[$normalized]] ??= $header;
            }
        }

        return $index;
    }
}
