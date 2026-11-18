<?php

namespace App\Services\Imports\Readers;

use Generator;

/**
 * A statement file seen as one table, whatever its file format.
 */
interface SheetReader
{
    /**
     * Search the top of the file for the header row.
     *
     * Statements often start with a title or address block. The first row
     * whose cells the callback accepts becomes the header row; when no row
     * is accepted the previous header row stays in place.
     *
     * @param  callable(list<string>): bool  $accepts
     */
    public function locateHeader(callable $accepts): bool;

    /**
     * @return list<string>
     */
    public function headers(): array;

    /**
     * Yield every data row below the header keyed by header, indexed by
     * its line number in the file.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(): Generator;
}
