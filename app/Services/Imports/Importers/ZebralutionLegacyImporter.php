<?php

namespace App\Services\Imports\Importers;

use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;

/**
 * Zebralution statement as issued before 2024 Q1.
 */
class ZebralutionLegacyImporter extends BaseImporter
{
    /** @var array<string, string> Content => format */
    private const array FORMATS = [
        'cloud' => 'Cloud/Locker',
        'download' => 'Download/Other',
        'radio' => 'Radio/Other',
        'subscription' => 'Subscription Audio Stream',
        'ringtone' => 'Digital/Other',
    ];

    public function key(): string
    {
        return 'zebralution-legacy';
    }

    public function source(): string
    {
        return 'zebralution';
    }

    public function sourceName(): string
    {
        return 'Zebralution';
    }

    public function version(): ?string
    {
        return 'legacy, before 2024 Q1';
    }

    public function description(): string
    {
        return 'The older statement layout with "Period Sold", "Provider", "Shop" and "Rev.less Publ.EUR" columns.';
    }

    public function requiresPaidDate(): bool
    {
        return true;
    }

    public function requiredColumns(): array
    {
        return ['Period Sold', 'Rev.less Publ.EUR', 'ISRC', 'Shop', 'Content'];
    }

    protected function map(Row $row): ImportRow
    {
        if ($row->string('Name Licensor') === null) {
            $this->skip('Summary line without licensor skipped.');
        }

        $content = $row->string('Content');

        return new ImportRow(
            total: $row->requireDecimal('Rev.less Publ.EUR'),
            timestamp: $row->requireMonthEnd('Period Sold'),
            currency: 'EUR',
            quantity: $row->decimal('Sales'),
            category: 'Distribution',
            format: self::FORMATS[strtolower((string) $content)] ?? 'Digital/Other',
            usageType: $content,
            region: $row->region('Country'),
            provider: 'Zebralution',
            retailer: $row->string('Shop', 'Provider'),
            source: 'Zebralution',
            artist: $row->string('Artist'),
            trackName: $row->string('Title'),
            catalogNumber: $row->string('Label Order-Nr'),
            upc: $row->upc('EAN'),
            isrc: $row->isrc('ISRC'),
            raw: $row->raw,
        );
    }
}
