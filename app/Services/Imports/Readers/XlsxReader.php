<?php

namespace App\Services\Imports\Readers;

use App\Services\Imports\ImportException;
use Carbon\CarbonImmutable;
use Generator;
use XMLReader;
use ZipArchive;

/**
 * Reads the first worksheet of an Excel workbook (.xlsx).
 *
 * Cells come back as text: numbers exactly as stored, dates as
 * "2026-03-31" (with a time when the cell has one), so importers can
 * treat a spreadsheet like a CSV file.
 */
class XlsxReader extends TabularReader
{
    /** Number formats Excel ships with that display a date or time. */
    private const array BUILT_IN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    private string $sheetPath;

    /** @var list<string> */
    private array $sharedStrings = [];

    /** @var array<int, bool> style index => shows a date */
    private array $dateStyles = [];

    private bool $uses1904Dates = false;

    public function __construct(private readonly string $path)
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true || $zip->locateName('xl/workbook.xml') === false) {
            throw new ImportException('The file could not be opened as an Excel workbook (.xlsx).');
        }

        $this->sheetPath = $this->firstSheetPath($zip);
        $this->sharedStrings = $this->readSharedStrings($zip);
        $this->dateStyles = $this->readDateStyles($zip);
        $this->uses1904Dates = (bool) preg_match('/<workbookPr[^>]*date1904="(1|true)"/', (string) $zip->getFromName('xl/workbook.xml'));

        $zip->close();
    }

    protected function lines(): Generator
    {
        $xml = new XMLReader;

        if (! @$xml->open('zip://'.$this->path.'#'.$this->sheetPath)) {
            throw new ImportException('The worksheet could not be read.');
        }

        try {
            $number = 0;

            while ($xml->read()) {
                if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'row') {
                    continue;
                }

                $number = (int) ($xml->getAttribute('r') ?: $number + 1);

                yield $number => $this->readRow($xml);
            }
        } finally {
            $xml->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readRow(XMLReader $xml): array
    {
        $cells = [];

        if ($xml->isEmptyElement) {
            return $cells;
        }

        $depth = $xml->depth;
        $next = 0;

        while ($xml->read() && $xml->depth > $depth) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'c') {
                continue;
            }

            $reference = (string) $xml->getAttribute('r');
            $column = $reference !== '' ? $this->columnIndex($reference) : $next;
            $type = (string) $xml->getAttribute('t');
            $style = (int) $xml->getAttribute('s');

            $cells[$column] = $this->cellValue($xml, $type, $style);
            $next = $column + 1;
        }

        if ($cells === []) {
            return [];
        }

        $row = [];
        $lastColumn = max(array_keys($cells));

        for ($column = 0; $column <= $lastColumn; $column++) {
            $row[] = $cells[$column] ?? '';
        }

        return $row;
    }

    private function cellValue(XMLReader $xml, string $type, int $style): string
    {
        if ($xml->isEmptyElement) {
            return '';
        }

        $depth = $xml->depth;
        $value = '';
        $text = '';

        while ($xml->read() && $xml->depth > $depth) {
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($xml->localName === 'v') {
                $value = $xml->readString();
            } elseif ($xml->localName === 't') {
                $text .= $xml->readString();
            }
        }

        $value = match ($type) {
            's' => $this->sharedStrings[(int) $value] ?? '',
            'inlineStr' => $text,
            'b' => $value === '1' ? 'true' : 'false',
            'e' => '',
            'str' => $value,
            default => ($this->dateStyles[$style] ?? false) && is_numeric($value) ? $this->date((float) $value) : $value,
        };

        return trim($value);
    }

    /**
     * Turn an Excel serial day number into an ISO date.
     */
    private function date(float $serial): string
    {
        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);
        $date = CarbonImmutable::create($this->uses1904Dates ? 1904 : 1899, $this->uses1904Dates ? 1 : 12, $this->uses1904Dates ? 1 : 30, 0, 0, 0, 'UTC')
            ->addDays($days)
            ->addSeconds($seconds);

        return $seconds === 0 ? $date->toDateString() : $date->toDateTimeString();
    }

    /**
     * "C7" → 2, "AA1" → 26.
     */
    private function columnIndex(string $reference): int
    {
        $index = 0;

        foreach (str_split(preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '') as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $relations = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (preg_match('/<(?:\w+:)?sheet\b[^>]*\br:id="([^"]+)"/', $workbook, $sheet)
            && preg_match('/<Relationship\b(?=[^>]*\bId="'.preg_quote($sheet[1], '/').'")[^>]*\bTarget="([^"]+)"/', $relations, $target)) {
            $path = str_starts_with($target[1], '/') ? ltrim($target[1], '/') : 'xl/'.$target[1];

            if ($zip->locateName($path) !== false) {
                return $path;
            }
        }

        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        throw new ImportException('The workbook has no worksheet.');
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = new XMLReader;
        $xml->XML($this->part($zip, 'xl/sharedStrings.xml'));
        $strings = [];

        while ($xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'si') {
                continue;
            }

            $text = '';

            if (! $xml->isEmptyElement) {
                $depth = $xml->depth;
                $phonetic = null;

                while ($xml->read() && $xml->depth > $depth) {
                    // Phonetic guides (rPh) repeat the text in another script and are not part of the value.
                    if ($phonetic !== null && $xml->depth <= $phonetic) {
                        $phonetic = null;
                    }

                    if ($xml->nodeType !== XMLReader::ELEMENT) {
                        continue;
                    }

                    if ($xml->localName === 'rPh') {
                        $phonetic = $xml->depth;
                    } elseif ($xml->localName === 't' && $phonetic === null) {
                        $text .= $xml->readString();
                    }
                }
            }

            $strings[] = $text;
        }

        $xml->close();

        return $strings;
    }

    /**
     * A part of the workbook that is read in one piece. A few kilobytes
     * can unpack to gigabytes, so its unpacked size is looked at first.
     */
    private function part(ZipArchive $zip, string $name): string
    {
        if (($zip->statName($name)['size'] ?? 0) > 200 * 1024 * 1024) {
            throw new ImportException('The workbook is too large to read. Save the sheet as CSV and import that.');
        }

        return (string) $zip->getFromName($name);
    }

    /**
     * @return array<int, bool>
     */
    private function readDateStyles(ZipArchive $zip): array
    {
        $styles = $this->part($zip, 'xl/styles.xml');

        if ($styles === '') {
            return [];
        }

        $custom = [];

        if (preg_match_all('/<numFmt\b[^>]*\bnumFmtId="(\d+)"[^>]*\bformatCode="([^"]*)"/', $styles, $formats, PREG_SET_ORDER)) {
            foreach ($formats as $format) {
                $code = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\./', '', html_entity_decode($format[2])) ?? '';
                $custom[(int) $format[1]] = (bool) preg_match('/[dmyhs]/i', $code);
            }
        }

        $dateStyles = [];

        if (preg_match('/<cellXfs\b[^>]*>(.*?)<\/cellXfs>/s', $styles, $cellFormats)
            && preg_match_all('/<xf\b[^>]*>/', $cellFormats[1], $entries)) {
            foreach ($entries[0] as $index => $entry) {
                $id = preg_match('/\bnumFmtId="(\d+)"/', $entry, $match) ? (int) $match[1] : 0;
                $dateStyles[$index] = $custom[$id] ?? in_array($id, self::BUILT_IN_DATE_FORMATS, true);
            }
        }

        return $dateStyles;
    }
}
