<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Database\Factories\StatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label_id', 'payee_id', 'period_id', 'revision_of_id', 'name', 'archived', 'published_at', 'balance', 'payable', 'data'])]
class Statement extends Model
{
    /** @use HasFactory<StatementFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived' => 'boolean',
            'published_at' => 'datetime',
            'data' => 'array',
            'balance' => 'decimal:15',
            'payable' => 'decimal:15',
        ];
    }

    /**
     * @return BelongsTo<Payee, $this>
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    /**
     * @return BelongsTo<Period, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * @return BelongsTo<Statement, $this>
     */
    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }
}
