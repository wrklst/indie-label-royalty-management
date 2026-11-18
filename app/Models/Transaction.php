<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $label_id
 * @property int|null $data_import_id
 * @property int|null $release_id
 * @property int|null $track_id
 * @property int|null $item_id
 * @property int|null $contract_id
 * @property int|null $payee_id
 * @property int|null $ledger_id
 * @property TransactionType $type
 * @property CarbonImmutable $timestamp
 * @property CarbonImmutable $paid_timestamp
 * @property string|float|int|null $quantity
 * @property string|float|int|null $price
 * @property numeric-string $total
 * @property string $currency
 * @property string|float|int|null $conversion_rate
 * @property numeric-string|null $converted_total
 * @property string|null $category
 * @property string|null $format
 * @property string|null $usage_type
 * @property string|null $region
 * @property string|null $provider
 * @property string|null $retailer
 * @property string|null $external_id
 * @property string|null $source
 * @property string|null $importer_source
 * @property string|null $memo
 * @property bool $published
 * @property bool $billable
 * @property bool $recoupable
 * @property string|null $fingerprint
 * @property array<string, mixed>|null $raw_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'data_import_id', 'release_id', 'track_id', 'item_id', 'contract_id', 'payee_id', 'ledger_id', 'type', 'timestamp', 'paid_timestamp', 'quantity', 'price', 'total', 'currency', 'conversion_rate', 'converted_total', 'category', 'format', 'usage_type', 'region', 'provider', 'retailer', 'external_id', 'source', 'importer_source', 'memo', 'published', 'billable', 'recoupable', 'fingerprint', 'raw_data'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'timestamp' => 'date:Y-m-d',
            'paid_timestamp' => 'date:Y-m-d',
            'total' => 'decimal:15',
            'converted_total' => 'decimal:15',
            'published' => 'boolean',
            'billable' => 'boolean',
            'recoupable' => 'boolean',
            'raw_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DataImport, $this>
     */
    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class);
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Payee, $this>
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    /**
     * @return BelongsTo<Ledger, $this>
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
