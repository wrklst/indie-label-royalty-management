<?php

namespace App\Services\Imports\Importers;

use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

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

    /** @var array<string, int>|null Synced sales no line of this file has accounted for yet, by sale key. */
    private ?array $synced = null;

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
        $this->synced = null;
    }

    /**
     * What identifies a sale in both the API and a downloaded report.
     */
    public static function saleKey(string $orderId, string $itemType, string $itemName): string
    {
        return $orderId.'|'.mb_strtolower(trim($itemType)).'|'.mb_strtolower(trim($itemName));
    }

    /**
     * Whether the sync has imported this line of a downloaded report
     * already. A report line carries no item id, so it is matched by
     * order, item type and item name; one synced sale accounts for one line.
     */
    private function wasSynced(string $orderId, string $itemType, string $itemName): bool
    {
        if ($this->synced === null) {
            $this->synced = [];

            // ponytail: reads every synced sale of the label once per file; narrow it to the file's order ids if uploads get slow.
            DB::table('transactions')
                ->where('label_id', $this->label->id)
                ->where('importer_source', $this->key())
                ->whereNotNull('external_id')
                ->where('raw_data', 'like', '%transaction_item_id%')
                ->orderBy('id')
                ->select(['id', 'external_id', 'raw_data'])
                ->lazy()
                ->each(function (object $transaction): void {
                    $raw = [];

                    foreach ((array) json_decode((string) $transaction->raw_data, true) as $column => $value) {
                        $raw[Row::normalize((string) $column)] = is_scalar($value) ? (string) $value : '';
                    }

                    if (($raw['bandcamp transaction item id'] ?? '') !== '') {
                        $key = self::saleKey((string) $transaction->external_id, $raw['item type'] ?? '', $raw['item name'] ?? '');
                        $this->synced[$key] = ($this->synced[$key] ?? 0) + 1;
                    }
                });
        }

        $key = self::saleKey($orderId, $itemType, $itemName);

        if (($this->synced[$key] ?? 0) === 0) {
            return false;
        }

        $this->synced[$key]--;

        return true;
    }

    protected function map(Row $row): ImportRow
    {
        $itemType = $row->lower('item type') ?? '';

        if ($itemType === 'payout') {
            $this->skip('Payout to your bank account is not a sale.');
        }

        $orderId = $row->string('bandcamp transaction id');

        if (
            $orderId !== null
            && $row->string('bandcamp transaction item id') === null
            && ($this->options['skip_duplicates'] ?? true)
            && $this->wasSynced($orderId, $itemType, (string) $row->string('item name'))
        ) {
            $this->skip('Sale already imported by the Bandcamp sync.');
        }

        $net = $row->decimal('net amount') ?? $this->skip('Line without a net amount skipped.');
        $isPhysical = ! in_array($itemType, ['album', 'track'], true);

        if ($isPhysical && $this->excludePhysical) {
            $this->skip('Physical sale skipped (label setting).');
        }

        // Raw reports use "8/1/23 1:43am", the API uses "21 Sep 2026 14:32:21 GMT".
        $date = $row->requireDate('date', 'n/j/y g:ia');
        $itemName = $row->string('item name');

        // "item total" is what the buyer paid, "amount you received" what was left after the
        // payment provider, and "net amount" what was left after Bandcamp's revenue share.
        $gross = $row->decimal('item total');
        $received = $row->decimal('amount you received');

        return new ImportRow(
            total: $this->disableFee ? $net : Decimal::mul($net, '0.99'),
            gross: $gross,
            paymentFee: $gross === null ? null : ($row->decimal('transaction fee') ?? ($received === null ? null : Decimal::sub($gross, $received))),
            distributionFee: $gross === null ? null : ($row->decimal('assessed revenue share') ?? ($received === null ? null : Decimal::sub($received, $net))),
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
