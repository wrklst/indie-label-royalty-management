<?php

namespace App\Services\Imports\Readers;

use Generator;

/**
 * Header detection and row mapping shared by all file formats. A format
 * only has to produce its lines as lists of cell values.
 */
abstract class TabularReader implements SheetReader
{
    /** How many lines from the top are searched for a header row. */
    private const int HEADER_SEARCH_LINES = 50;

    private ?int $headerLine = null;

    /** @var list<string> */
    private array $headers = [];

    /**
     * Yield every line of the file from the top, as trimmed cell values
     * indexed by the 1-based line number. Must be restartable.
     *
     * @return Generator<int, list<string>>
     */
    abstract protected function lines(): Generator;

    public function locateHeader(callable $accepts): bool
    {
        foreach ($this->lines() as $number => $cells) {
            if ($number > self::HEADER_SEARCH_LINES) {
                break;
            }

            if ($this->isBlank($cells)) {
                continue;
            }

            if ($accepts($cells)) {
                $this->headerLine = $number;
                $this->headers = $cells;

                return true;
            }
        }

        return false;
    }

    public function headers(): array
    {
        if ($this->headerLine === null) {
            $this->locateHeader(fn (): bool => true);
        }

        return $this->headers;
    }

    public function rows(): Generator
    {
        $headers = $this->headers();
        $count = count($headers);

        foreach ($this->lines() as $number => $cells) {
            if ($number <= $this->headerLine || $this->isBlank($cells)) {
                continue;
            }

            yield $number => array_combine($headers, array_slice(array_pad($cells, $count, ''), 0, $count));
        }
    }

    /**
     * @param  list<string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        return trim(implode('', $cells)) === '';
    }
}
