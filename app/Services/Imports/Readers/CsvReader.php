<?php

namespace App\Services\Imports\Readers;

use App\Services\Imports\ImportException;
use Generator;

/**
 * Reads delimited text files regardless of encoding or delimiter.
 *
 * Bandcamp exports UTF-16, Zebralution UTF-8 with BOM and semicolons, so
 * both encoding and delimiter are detected from the file itself.
 */
class CsvReader extends TabularReader
{
    /** @var resource */
    private $stream;

    private string $delimiter;

    public function __construct(string $path)
    {
        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw new ImportException('The file is empty or could not be read.');
        }

        $contents = $this->toUtf8($contents);
        $this->delimiter = $this->detectDelimiter($contents);

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new ImportException('The file could not be read.');
        }

        fwrite($stream, $contents);
        $this->stream = $stream;
    }

    protected function lines(): Generator
    {
        rewind($this->stream);
        $number = 0;

        while (($cells = fgetcsv($this->stream, null, $this->delimiter, '"', '')) !== false) {
            $number++;

            yield $number => array_map(fn ($value): string => trim((string) $value), $cells);
        }
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        return mb_check_encoding($contents, 'UTF-8')
            ? $contents
            : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    /**
     * Pick the delimiter that splits the first lines into the most cells.
     * Several lines are sampled because a title line may hold none.
     */
    private function detectDelimiter(string $contents): string
    {
        $sample = implode("\n", array_slice(preg_split('/\R/', substr($contents, 0, 65536)) ?: [], 0, 20));
        $unquoted = preg_replace('/"[^"]*"/', '', $sample) ?? $sample;
        $counts = [];

        foreach ([';', ',', "\t", '|'] as $candidate) {
            $counts[$candidate] = substr_count($unquoted, $candidate);
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
