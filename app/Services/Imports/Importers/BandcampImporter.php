<?php

namespace App\Services\Imports\Importers;

use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;
use App\Support\Decimal;

/**
 * Bandcamp sales report, either the raw download from the Bandcamp
 * tools page or the report produced from the sales API.
 */
class BandcampImporter extends BaseImporter
{
    /** @var list<string> Columns holding buyer data that must never be stored. */
    private const array PERSONAL_COLUMNS = [
        'paid to', 'buyer name', 'buyer email', 'buyer phone', 'buyer note', 'ship to name', 'ship to street',
        'ship to street 2', 'ship to city', 'ship to state', 'ship to zip', 'ship notes', 'paypal transaction id',
        'city', 'region/state', 'region or state',
    ];

    private bool $disableFee = false;

    private bool $excludePhysical = false;

    public function key(): string
    {
        return 'bandcamp';
    }

    public function sourceName(): string
    {
        return 'Bandcamp';
    }

    public function description(): string
    {
        return 'Raw sales report (Tools → Sales report) or sales API export. Buyer names, emails and addresses are discarded.';
    }

    public function requiredColumns(): array
    {
        return ['date', 'item type', 'item name', 'net amount', 'bandcamp transaction id'];
    }

    protected function prepare(): void
    {
        $this->disableFee = (bool) ($this->options['disable_fee'] ?? $this->label->importerSetting('bandcamp.disable_fee', false));
        $this->excludePhysical = (bool) ($this->options['exclude_physical'] ?? $this->label->importerSetting('bandcamp.exclude_physical', false));
    }

    protected function map(Row $row): ImportRow
    {
        $itemType = $row->lower('item type') ?? '';

        if ($itemType === 'payout') {
            $this->skip('Payout to your bank account is not a sale.');
        }

        $net = $row->decimal('net amount') ?? $this->skip('Line without a net amount skipped.');
        $isPhysical = ! in_array($itemType, ['album', 'track'], true);

        if ($isPhysical && $this->excludePhysical) {
            $this->skip('Physical sale skipped (label setting).');
        }

        // Raw reports use "8/1/23 1:43am", the API uses "21 Sep 2026 14:32:21 GMT".
        $date = $row->requireDate('date', 'n/j/y g:ia');
        $itemName = $row->string('item name');

        return new ImportRow(
            total: $this->disableFee ? $net : Decimal::mul($net, '0.99'),
            timestamp: $date,
            paidTimestamp: $date,
            currency: $row->upper('currency'),
            quantity: $row->decimal('quantity'),
            price: $row->decimal('item price'),
            category: 'Direct to Fan/Webstore',
            format: match ($itemType) {
                'album' => 'Download Album',
                'track' => 'Download Track',
                default => 'Physical/Other',
            },
            usageType: $row->string('package'),
            region: $row->region('country code'),
            provider: 'Bandcamp',
            retailer: 'Bandcamp',
            externalId: $row->string('bandcamp transaction id'),
            source: 'Bandcamp',
            memo: $isPhysical ? $itemName : null,
            artist: $row->string('artist'),
            releaseName: $itemType === 'track' ? null : $itemName,
            trackName: $itemType === 'track' ? $itemName : null,
            catalogNumber: $row->string('catalog number'),
            upc: $row->upc('upc'),
            isrc: $row->isrc('isrc'),
            raw: $row->rawWithout(self::PERSONAL_COLUMNS),
        );
    }
}
