<?php

namespace App\Services\Imports\Importers;

/**
 * Neighbouring rights income, e.g. statements from Rightz Audio.
 *
 * Collecting societies do not share one file layout, so this reads the
 * spreadsheet template and fills in the rights specific defaults.
 */
class RightsImporter extends TemplateImporter
{
    public function key(): string
    {
        return 'audio-rights';
    }

    public function sourceName(): string
    {
        return 'Audio rights (neighbouring rights)';
    }

    public function description(): string
    {
        return 'Neighbouring rights income such as Rightz Audio, in the template layout. Category "Rights", format "Neighboring Rights" and the platform are filled in for you.';
    }

    public function requiresPaidDate(): bool
    {
        return true;
    }

    /**
     * Its layout is the template's, so it is only used when chosen.
     */
    public function priority(): int
    {
        return -110;
    }

    protected function defaults(): array
    {
        return [
            'transaction type' => 'income',
            'category' => 'Rights',
            'format' => 'Neighboring Rights',
            'retailer' => 'Rightz Audio',
            'source' => 'Rightz Audio',
        ];
    }
}
