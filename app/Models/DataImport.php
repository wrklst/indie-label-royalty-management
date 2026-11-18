<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\BelongsToLabel;
use Database\Factories\DataImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label_id', 'user_id', 'importer', 'name', 'status', 'original_filename', 'file_path', 'file_hash', 'default_timestamp', 'replace_timestamp', 'default_paid_timestamp', 'replace_paid_timestamp', 'needs_cleanup', 'tags', 'options', 'rows_count', 'transactions_count', 'duplicates_count', 'errors_count', 'created_releases_count', 'created_tracks_count', 'total', 'errors', 'metadata'])]
class DataImport extends Model
{
    /** @use HasFactory<DataImportFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'default_timestamp' => 'date:Y-m-d',
            'default_paid_timestamp' => 'date:Y-m-d',
            'replace_timestamp' => 'boolean',
            'replace_paid_timestamp' => 'boolean',
            'needs_cleanup' => 'boolean',
            'tags' => 'array',
            'options' => 'array',
            'errors' => 'array',
            'metadata' => 'array',
            'total' => 'decimal:15',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
