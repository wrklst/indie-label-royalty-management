<?php

namespace App\Services\Imports\Importers;

use App\Enums\TransactionType;
use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;

/**
 * Generic spreadsheet import using the bulk transaction template.
 */
class TemplateImporter extends BaseImporter
{
    /** @var list<string> */
    public const array COLUMNS = [
        'timestamp', 'paid timestamp', 'transaction type', 'total', 'currency', 'payee', 'artist', 'release name',
        'track name', 'catalog number', 'isrc', 'upc', 'item id', 'contract id', 'memo', 'source', 'category', 'format',
        'quantity', 'price', 'retailer', 'external id', 'region',
    ];

    public function key(): string
    {
        return 'template';
    }

    public function sourceName(): string
    {
        return 'Spreadsheet template';
    }

    public function description(): string
    {
        return 'Income, expenses and transfers in the template layout, as CSV or Excel. Only "total" is required; costs are negative amounts.';
    }

    /**
     * Any table with a "total" column fits the template, so every other
     * importer gets to recognise the file first.
     */
    public function priority(): int
    {
        return -100;
    }

    public function requiredColumns(): array
    {
        return ['total'];
    }

    protected function aliases(): array
    {
        return [
            'date' => 'timestamp', 'paid date' => 'paid timestamp', 'paid' => 'paid timestamp', 'type' => 'transaction type',
            'amount' => 'total', 'release' => 'release name', 'album' => 'release name', 'track' => 'track name',
            'title' => 'track name', 'cat no' => 'catalog number', 'catalogue number' => 'catalog number', 'ean' => 'upc',
            'country' => 'region', 'territory' => 'region', 'units' => 'quantity', 'shop' => 'retailer', 'store' => 'retailer',
            'description' => 'memo', 'notes' => 'memo',
        ];
    }

    /**
     * Values applied when a line leaves a column empty.
     *
     * @return array<string, string>
     */
    protected function defaults(): array
    {
        return [];
    }

    protected function map(Row $row): ImportRow
    {
        $defaults = $this->defaults();
        $get = fn (string $column): ?string => $row->string($column) ?? ($defaults[$column] ?? null);

        $total = $row->requireDecimal('total');
        $type = TransactionType::tryFrom(strtolower($get('transaction type') ?? 'income'))
            ?? $this->fail('"transaction type" must be income, expense or transfer.');

        if ($type === TransactionType::Transfer && $get('payee') === null) {
            $this->fail('A transfer needs a payee.');
        }

        return new ImportRow(
            total: $total,
            type: $type,
            timestamp: $row->date('timestamp'),
            paidTimestamp: $row->date('paid timestamp'),
            currency: $get('currency') ? strtoupper($get('currency')) : null,
            quantity: $row->decimal('quantity'),
            price: $row->decimal('price'),
            category: $get('category'),
            format: $get('format'),
            region: $row->region('region'),
            provider: $get('provider') ?? $get('retailer'),
            retailer: $get('retailer'),
            externalId: $get('external id'),
            source: $get('source'),
            memo: $get('memo'),
            artist: $get('artist'),
            releaseName: $get('release name'),
            trackName: $get('track name'),
            catalogNumber: $get('catalog number'),
            upc: $row->upc('upc'),
            isrc: $row->isrc('isrc'),
            payeeName: $get('payee'),
            itemId: $row->integer('item id'),
            contractId: $row->integer('contract id'),
            raw: $row->raw,
        );
    }
}
