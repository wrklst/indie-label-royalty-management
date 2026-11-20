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

    /**
     * What Bandcamp sales in the ledger were booked as: by this importer
     * and the sync, or carried over from another royalty system that
     * fetched them from Bandcamp ("bandcamp_api").
     *
     * @var list<string>
     */
    public const array SOURCES = ['bandcamp', 'bandcamp_api'];

    private bool $disableFee = false;

    private bool $excludePhysical = false;

    /** @var array{items: array<string, true>, synced: array<string, int>, reported: array<string, int>, migrated: array<string, int>}|null Sales in the ledger no line of this file has accounted for yet. */
    private ?array $known = null;

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
        $this->known = null;
    }

    /**
     * The format a sale is booked under.
     */
    public static function format(string $itemType): string
    {
        return match (mb_strtolower(trim($itemType))) {
            'album' => 'Download Album',
            'track' => 'Download Track',
            default => 'Physical/Other',
        };
    }

    /**
     * The Bandcamp sales of a label that are in the ledger, sorted by
     * how they can be recognised again:
     *
     * - "items": fetched by the sync, by order id and item id.
     * - "synced": the same sales, counted by order, item type and item name, for report lines that carry no item id.
     * - "reported": from an uploaded report, counted by order, item type and item name.
     * - "migrated": carried over from another system without the line Bandcamp sent, counted by order id and format.
     *
     * @param  list<string>|null  $orderIds  Only these orders; null reads every sale.
     * @return array{items: array<string, true>, synced: array<string, int>, reported: array<string, int>, migrated: array<string, int>}
     */
    public static function known(int $labelId, ?array $orderIds = null): array
    {
        $known = ['items' => [], 'synced' => [], 'reported' => [], 'migrated' => []];

        DB::table('transactions')
            ->where('label_id', $labelId)
            ->whereIn('importer_source', self::SOURCES)
            ->whereNotNull('external_id')
            ->when($orderIds !== null, fn ($query) => $query->whereIn('external_id', $orderIds))
            ->orderBy('id')
            ->select(['id', 'external_id', 'format', 'raw_data'])
            ->lazy()
            ->each(function (object $transaction) use (&$known): void {
                $raw = [];

                foreach ((array) json_decode((string) $transaction->raw_data, true) as $column => $value) {
                    $raw[Row::normalize((string) $column)] = is_scalar($value) ? (string) $value : '';
                }

                $orderId = (string) $transaction->external_id;
                $itemId = $raw['bandcamp transaction item id'] ?? '';
                $sale = self::saleKey($orderId, $raw['item type'] ?? '', $raw['item name'] ?? '');

                if ($itemId !== '') {
                    $known['items']["{$orderId}:{$itemId}"] = true;
                    $known['synced'][$sale] = ($known['synced'][$sale] ?? 0) + 1;
                } elseif (isset($raw['item type']) || isset($raw['item name'])) {
                    $known['reported'][$sale] = ($known['reported'][$sale] ?? 0) + 1;
                } else {
                    $key = self::migratedKey($orderId, (string) $transaction->format);
                    $known['migrated'][$key] = ($known['migrated'][$key] ?? 0) + 1;
                }
            });

        return $known;
    }

    /**
     * What identifies a migrated sale: all that is left of it is the order and what kind of item it was.
     */
    public static function migratedKey(string $orderId, string $format): string
    {
        return $orderId.'|'.$format;
    }

    /**
     * What identifies a sale in both the API and a downloaded report.
     */
    public static function saleKey(string $orderId, string $itemType, string $itemName): string
    {
        return $orderId.'|'.mb_strtolower(trim($itemType)).'|'.mb_strtolower(trim($itemName));
    }

    /**
     * Whether this line of a downloaded report is in the ledger already:
     * fetched by the sync, or carried over from another system. A report
     * line carries no item id, so it is matched by order, item type and
     * item name, or by order and format; one booked sale accounts for one line.
     */
    private function isKnown(string $orderId, string $itemType, string $itemName): bool
    {
        // ponytail: reads every Bandcamp sale of the label once per file; narrow it to the file's order ids if uploads get slow.
        $this->known ??= self::known($this->label->id);

        foreach (['synced' => self::saleKey($orderId, $itemType, $itemName), 'migrated' => self::migratedKey($orderId, self::format($itemType))] as $kind => $key) {
            if (($this->known[$kind][$key] ?? 0) > 0) {
                $this->known[$kind][$key]--;

                return true;
            }
        }

        return false;
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
            && $this->isKnown($orderId, $itemType, (string) $row->string('item name'))
        ) {
            $this->skip('Sale already booked: fetched by the Bandcamp sync or carried over from another system.');
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
            format: self::format($itemType),
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
