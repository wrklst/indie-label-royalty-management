<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToLabel;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label_id', 'data_import_id', 'release_id', 'track_id', 'item_id', 'contract_id', 'payee_id', 'ledger_id', 'type', 'timestamp', 'paid_timestamp', 'quantity', 'price', 'total', 'currency', 'conversion_rate', 'converted_total', 'category', 'format', 'usage_type', 'region', 'provider', 'retailer', 'external_id', 'source', 'importer_source', 'memo', 'published', 'billable', 'fingerprint', 'raw_data'])]
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
