<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\Receipts\ReceiptReading;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Has one uploaded receipt read by the label's AI service.
 */
class ReadReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $receiptId) {}

    public function handle(ReceiptReading $reading): void
    {
        $receipt = $this->receipt();

        if ($receipt !== null) {
            $reading->read($receipt);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->receipt()?->update([
            'status' => Receipt::FAILED,
            'error' => mb_substr($exception?->getMessage() ?? 'Reading stopped unexpectedly.', 0, 500),
        ]);
    }

    private function receipt(): ?Receipt
    {
        return Receipt::query()->withoutGlobalScope('label')->find($this->receiptId);
    }
}
