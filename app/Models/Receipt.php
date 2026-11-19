<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded receipt or invoice for a label cost. It waits to be booked
 * until it is attached to a cost; what was read off it is a proposal.
 *
 * @property int $id
 * @property int $label_id
 * @property int|null $user_id
 * @property int|null $transaction_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $status
 * @property string|null $vendor
 * @property CarbonImmutable|null $paid_on
 * @property numeric-string|null $amount
 * @property string|null $currency
 * @property string|null $category
 * @property string|null $summary
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'user_id', 'transaction_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'status', 'vendor', 'paid_on', 'amount', 'currency', 'category', 'summary', 'error'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use BelongsToLabel, HasFactory;

    /** Uploaded, not read. */
    public const string NEW = 'new';

    /** Queued for or being read. */
    public const string READING = 'reading';

    public const string READ = 'read';

    public const string FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount' => 'decimal:15',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
