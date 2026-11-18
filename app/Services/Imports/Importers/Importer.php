<?php

namespace App\Services\Imports\Importers;

use App\Models\Label;
use App\Services\Imports\ImportException;
use App\Services\Imports\ImportRow;
use App\Services\Imports\ImportRowError;
use App\Services\Imports\Readers\SheetReader;
use Generator;

/**
 * Turns one file layout of one source into transactions.
 *
 * A source (Zebralution, Bandcamp, …) can have several importers, one per
 * layout it has used over time. Extend BaseImporter rather than
 * implementing this directly.
 */
interface Importer
{
    /**
     * Unique and permanent: stored with every import and used to
     * recognise lines that were imported before.
     */
    public function key(): string;

    /**
     * Groups the versions of one source, e.g. "zebralution".
     */
    public function source(): string;

    public function sourceName(): string;

    /**
     * What distinguishes this layout, e.g. "2024 Q1 onwards". Null when
     * the source has only one layout.
     */
    public function version(): ?string;

    public function name(): string;

    public function description(): string;

    /**
     * Whether the source file lacks a payment date, so the user must supply one.
     */
    public function requiresPaidDate(): bool;

    /**
     * Importers with a higher priority are tried first when the layout
     * of a file is detected.
     */
    public function priority(): int;

    /**
     * The columns a file must have to be in this layout.
     *
     * @return list<string>
     */
    public function requiredColumns(): array;

    /**
     * Whether the file is in this layout. Positions the reader on the
     * header row when it is.
     */
    public function matches(SheetReader $reader): bool;

    /**
     * Turn the rows of a file into normalised import rows.
     *
     * @param  array<string, mixed>  $options
     * @return Generator<int, ImportRow|ImportRowError>
     *
     * @throws ImportException when the file is not in this importer's layout
     */
    public function rows(SheetReader $reader, Label $label, array $options = []): Generator;
}
