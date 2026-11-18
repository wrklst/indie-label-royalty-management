<?php

namespace App\Services\Imports;

use App\Services\Imports\Importers\Importer;
use App\Services\Imports\Readers\SheetReader;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Knows every importer and picks the one that fits a file.
 *
 * Importers are discovered: any instantiable class implementing Importer
 * below app/Services/Imports/Importers is available without registration.
 */
class ImporterRegistry
{
    /** Chosen in place of a source to have the file recognised by its columns. */
    public const string AUTO = 'auto';

    /** @var array<string, Importer>|null */
    private ?array $importers = null;

    /**
     * Every importer by key, in the order they are tried on a file.
     *
     * @return array<string, Importer>
     */
    public function all(): array
    {
        if ($this->importers !== null) {
            return $this->importers;
        }

        $directory = app_path('Services/Imports/Importers');
        $found = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
            $class = __NAMESPACE__.'\\Importers\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($class) || ! is_subclass_of($class, Importer::class) || ! new ReflectionClass($class)->isInstantiable()) {
                continue;
            }

            $importer = app($class);

            if (isset($found[$importer->key()])) {
                throw new ImportException("Two importers use the key [{$importer->key()}]: ".$found[$importer->key()]::class." and {$class}.");
            }

            $found[$importer->key()] = $importer;
        }

        uasort($found, fn (Importer $a, Importer $b): int => $b->priority() <=> $a->priority());

        return $this->importers = $found;
    }

    public function find(string $key): ?Importer
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Everything a user may pick for an upload: automatic detection, a
     * source, or one specific layout.
     *
     * @return list<string>
     */
    public function choices(): array
    {
        return array_values(array_unique([
            self::AUTO,
            ...array_map(fn (Importer $importer): string => $importer->source(), array_values($this->all())),
            ...$this->keys(),
        ]));
    }

    /**
     * The importers a choice stands for, in the order they are tried.
     *
     * @return list<Importer>
     */
    public function candidates(string $choice): array
    {
        $all = array_values($this->all());

        if ($choice === self::AUTO) {
            return $all;
        }

        $ofSource = array_values(array_filter($all, fn (Importer $importer): bool => $importer->source() === $choice));

        if ($ofSource !== []) {
            return $ofSource;
        }

        return array_values(array_filter($all, fn (Importer $importer): bool => $importer->key() === $choice));
    }

    /**
     * Pick the importer whose layout the file has.
     *
     * @throws ImportException when no layout of the choice fits the file
     */
    public function resolve(string $choice, SheetReader $reader): Importer
    {
        $candidates = $this->candidates($choice);

        foreach ($candidates as $importer) {
            if ($importer->matches($reader)) {
                return $importer;
            }
        }

        if ($candidates === []) {
            throw new ImportException("Unknown importer [{$choice}].");
        }

        if ($choice === self::AUTO) {
            throw new ImportException('The file was not recognised as a statement of any known source. Choose the source it comes from, or bring it into the spreadsheet template layout.');
        }

        $expected = array_map(
            fn (Importer $importer): string => ($importer->version() ?? $importer->name()).': "'.implode('", "', $importer->requiredColumns()).'"',
            $candidates,
        );

        throw new ImportException(sprintf(
            'This file is not in a known %s layout. The header row must contain these columns – %s. Found: %s.',
            $candidates[0]->sourceName(),
            implode('; or ', $expected),
            implode(', ', array_filter($reader->headers())) ?: 'no header row',
        ));
    }

    /**
     * @return list<array{key: string, name: string, description: string, requires_paid_date: bool}>
     */
    public function describe(): array
    {
        return array_values(array_map(fn (Importer $importer): array => [
            'key' => $importer->key(),
            'name' => $importer->name(),
            'description' => $importer->description(),
            'requires_paid_date' => $importer->requiresPaidDate(),
        ], $this->all()));
    }

    /**
     * The sources with their layouts, for the upload form.
     *
     * @return list<array{key: string, name: string, requires_paid_date: bool, versions: list<array{key: string, version: string|null, description: string, columns: list<string>}>}>
     */
    public function sources(): array
    {
        $sources = [];

        foreach ($this->all() as $importer) {
            $sources[$importer->source()] ??= [
                'key' => $importer->source(),
                'name' => $importer->sourceName(),
                'requires_paid_date' => false,
                'versions' => [],
            ];

            $sources[$importer->source()]['requires_paid_date'] = $sources[$importer->source()]['requires_paid_date'] || $importer->requiresPaidDate();
            $sources[$importer->source()]['versions'][] = [
                'key' => $importer->key(),
                'version' => $importer->version(),
                'description' => $importer->description(),
                'columns' => $importer->requiredColumns(),
            ];
        }

        return array_values($sources);
    }
}
