<?php

namespace App\Services\Receipts;

use App\Ai\Agents\ReceiptReader;
use App\Models\Label;
use App\Models\Receipt;
use App\Support\Decimal;
use App\Support\WorkingCopy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Files\Image;
use RuntimeException;
use Throwable;

/**
 * Has the label's AI service read a receipt and stores what it found as
 * a proposal. Images are sent as they are. A PDF is sent as its text;
 * a scanned one, which has none, as a picture of its first page.
 */
class ReceiptReading
{
    /** More text than any receipt has; a longer document is cut off here. */
    private const int MAX_TEXT = 20000;

    private const int TIMEOUT = 120;

    /**
     * @throws RuntimeException when the receipt could not be read
     */
    public function read(Receipt $receipt): void
    {
        $label = Label::query()->findOrFail($receipt->label_id);
        $provider = AiConnection::provider($label);
        $file = WorkingCopy::of($receipt->disk, $receipt->path);
        $picture = null;

        try {
            if ($receipt->mime_type === 'application/pdf') {
                $text = $this->pdfText($file->path);
                $picture = $text === '' ? $this->pdfPicture($file->path) : null;
                $prompt = $text === '' ? 'Read this receipt.' : "Read this receipt. Its text:\n\n".mb_substr($text, 0, self::MAX_TEXT);
                $attachments = $picture === null ? [] : [Image::fromPath($picture, 'image/png')];
            } else {
                $prompt = 'Read this receipt.';
                $attachments = [Image::fromPath($file->path, $receipt->mime_type)];
            }

            $read = (new ReceiptReader(config('royalties.categories.overhead')))
                ->prompt($prompt, $attachments, provider: [$provider], model: AiConnection::model($label), timeout: self::TIMEOUT)
                ->toArray();
        } finally {
            if ($picture !== null) {
                @unlink($picture);
            }
        }

        $receipt->update([
            'status' => Receipt::READ,
            'error' => null,
            'vendor' => $this->text($read['vendor'] ?? null, 255),
            'paid_on' => $this->date($read['date'] ?? null),
            'amount' => $this->amount($read['total'] ?? null),
            'currency' => preg_match('/^[A-Za-z]{3}$/', trim((string) ($read['currency'] ?? ''))) === 1 ? strtoupper(trim((string) $read['currency'])) : null,
            'category' => in_array($read['category'] ?? null, config('royalties.categories.overhead'), true) ? $read['category'] : null,
            'summary' => $this->text($read['summary'] ?? null, 500),
        ]);
    }

    private function pdfText(string $path): string
    {
        $result = Process::timeout(30)->run(['pdftotext', '-layout', '-l', '3', $path, '-']);

        return $result->successful() ? trim($result->output()) : '';
    }

    /**
     * The first page as a PNG file the caller removes.
     *
     * @throws RuntimeException when the page cannot be rendered
     */
    private function pdfPicture(string $path): string
    {
        $target = tempnam(sys_get_temp_dir(), 'receipt');

        if ($target === false || Process::timeout(60)->run(['pdftoppm', '-png', '-r', '110', '-f', '1', '-l', '1', '-singlefile', $path, $target])->failed()) {
            throw new RuntimeException('This PDF has no text and could not be turned into a picture. Install poppler-utils (pdftotext, pdftoppm) on the server.');
        }

        @unlink($target);

        return $target.'.png';
    }

    private function text(mixed $value, int $length): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $length) : null;
    }

    private function date(mixed $value): ?string
    {
        try {
            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', trim($value))->toDateString()
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return numeric-string|null
     */
    private function amount(mixed $value): ?string
    {
        $amount = Decimal::parse(is_string($value) || is_numeric($value) ? str_replace([' ', "'"], '', (string) $value) : null);

        return $amount === null || Decimal::cmp($amount, '0') <= 0 ? null : $amount;
    }
}
