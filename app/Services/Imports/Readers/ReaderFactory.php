<?php

namespace App\Services\Imports\Readers;

use App\Services\Imports\ImportException;

/**
 * Opens a statement file with the reader for its format. The format is
 * taken from the file contents, not the file name.
 */
class ReaderFactory
{
    public function open(string $path): SheetReader
    {
        $start = (string) @file_get_contents($path, false, null, 0, 8);

        if ($start === '') {
            throw new ImportException('The file is empty or could not be read.');
        }

        if (str_starts_with($start, "PK\x03\x04")) {
            return new XlsxReader($path);
        }

        if (str_starts_with($start, "\xD0\xCF\x11\xE0")) {
            throw new ImportException('Old Excel files (.xls) are not supported. Save the file as .xlsx or CSV and try again.');
        }

        return new CsvReader($path);
    }
}
