<?php

namespace App\Services\Imports\Importers;

use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;
use App\Support\Decimal;

/**
 * Zebralution "statement_label_track" file, in use since 2024 Q1.
 *
 * One line per track, shop, usage type and country. The file carries no
 * payment date.
 */
class ZebralutionImporter extends BaseImporter
{
    /** @var array<string, string> Income Type => format */
    private const array FORMATS = [
        'breakage' => 'Breakage (Digital)',
        'cloud' => 'Cloud/Locker',
        'digital-sale' => 'Download Track',
        'micro-creation' => 'Creation',
        'micro-creation-consumption' => 'Creation',
        'performance' => 'Performance Rights',
        'streaming' => 'Streaming/Other',
    ];

    public function key(): string
    {
        return 'zebralution';
    }

    public function sourceName(): string
    {
        return 'Zebralution';
    }

    public function version(): ?string
    {
        return 'track statement, 2024 Q1 onwards';
    }

    public function description(): string
    {
        return 'Quarterly "statement_label_track" file from the Zebralution licensor portal.';
    }

    public function requiresPaidDate(): bool
    {
        return true;
    }

    public function requiredColumns(): array
    {
        return ['DSP Reporting Period', 'Licensor Amount in EUR', 'ISRC', 'Income Type', 'Shop Name'];
    }

    protected function map(Row $row): ImportRow
    {
        $usageType = $row->string('Usage Type');
        $total = $row->requireDecimal('Licensor Amount in EUR');
        // What the shop paid Zebralution, and the share of it the contract leaves to the label, in percent.
        $gross = $row->decimal('Amount Distributable in EUR');
        $share = $row->decimal('Contract Share');

        return new ImportRow(
            total: $total,
            gross: $gross,
            distributionFee: $gross === null || $share === null ? null : Decimal::div(Decimal::mul($gross, Decimal::sub('100', $share)), '100'),
            timestamp: $row->requireMonthEnd('DSP Reporting Period'),
            currency: 'EUR',
            quantity: $row->decimal('Quantity Integer', 'Quantity'),
            category: 'Distribution',
            format: $this->format($row->lower('Income Type') ?? '', $usageType ?? ''),
            usageType: $usageType,
            region: $row->region('Country'),
            provider: 'Zebralution',
            retailer: $row->string('Shop Name', 'DSP Group'),
            source: 'Zebralution',
            artist: $row->string('Track Display Artist'),
            releaseArtist: $row->string('Product Display Artist'),
            releaseName: $this->title($row->string('Product Title'), $row->string('Product Version')),
            trackName: $this->title($row->string('Track Title'), $row->string('Track Version')),
            catalogNumber: $row->string('Product Catalog Number'),
            upc: $row->upc('EAN'),
            isrc: $row->isrc('ISRC'),
            raw: $row->raw,
        );
    }

    /**
     * Map the income type to a format, splitting streams into ad-supported
     * and subscription where the usage type says so.
     */
    private function format(string $incomeType, string $usageType): string
    {
        if ($incomeType === 'streaming') {
            if (stripos($usageType, 'Ad Supported') !== false) {
                return 'Ad-Supported Audio Stream';
            }

            if (stripos($usageType, 'Subscription') !== false) {
                return 'Subscription Audio Stream';
            }
        }

        return self::FORMATS[$incomeType] ?? 'Digital/Other';
    }

    private function title(?string $title, ?string $version): ?string
    {
        if ($title === null) {
            return null;
        }

        return $version === null ? $title : "{$title} ({$version})";
    }
}
