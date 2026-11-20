<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Jobs\ReadReceipt;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Services\Receipts\AiConnection;
use App\Support\CurrentLabel;
use App\Support\IncomingFile;
use App\Support\WorkingCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receipts and invoices for label costs: uploaded first, then booked as
 * a new cost or attached to one that exists.
 */
class ReceiptController extends Controller
{
    private const int LINK_MINUTES = 10;

    private const int MAX_KILOBYTES = 20480;

    /** @var list<string> */
    private const array TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /**
     * Store an uploaded file and, when the label has an AI service, have it read.
     * The page sends several files one after the other.
     */
    public function store(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $request->validate([
            'file' => ['required_without:upload', 'file', 'max:'.self::MAX_KILOBYTES, 'mimetypes:'.implode(',', self::TYPES)],
            ...IncomingFile::RULES,
        ], [
            'file.mimetypes' => __('A receipt has to be a PDF, JPEG, PNG or WebP file.'),
            'file.max' => __('A receipt can be 20 MB at most.'),
        ]);

        $label = $currentLabel->get() ?? abort(404);
        $reads = AiConnection::isConfigured($label);
        $disk = WorkingCopy::disk();
        $file = IncomingFile::from($request, $label->id, self::MAX_KILOBYTES, self::TYPES, messages: [
            'type' => __('A receipt has to be a PDF, JPEG, PNG or WebP file.'),
            'max' => __('A receipt can be 20 MB at most.'),
        ]);

        $receipt = Receipt::query()->create([
            'user_id' => $request->user()->id,
            'disk' => $disk,
            'path' => $file->storeAs("receipts/{$label->id}", Str::uuid().'.'.$file->extension(), $disk),
            'original_name' => $file->name,
            'mime_type' => (string) $file->mimeType,
            'size' => $file->size,
            'status' => $reads ? Receipt::READING : Receipt::NEW,
        ]);

        if ($reads) {
            ReadReceipt::dispatch($receipt->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $reads ? __('Receipt uploaded. It is being read.') : __('Receipt uploaded.')]);

        return back();
    }

    /**
     * Show the file to an admin of its label.
     */
    public function show(Receipt $receipt): Response
    {
        $disposition = 'inline; filename="'.addcslashes($receipt->original_name, '"\\').'"';

        if (! WorkingCopy::isLocal($receipt->disk)) {
            return redirect()->away(Storage::disk($receipt->disk)->temporaryUrl($receipt->path, now()->addMinutes(self::LINK_MINUTES), [
                'ResponseContentType' => $receipt->mime_type,
                'ResponseContentDisposition' => $disposition,
                'ResponseCacheControl' => 'private, no-store',
            ]))->header('Cache-Control', 'private, no-store');
        }

        abort_unless(Storage::disk($receipt->disk)->exists($receipt->path), 404);

        return response()->file(Storage::disk($receipt->disk)->path($receipt->path), [
            'Content-Type' => $receipt->mime_type,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Attach the receipt to a label cost, or take it off one.
     */
    public function update(Request $request, Receipt $receipt, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['nullable', 'integer', Rule::exists('transactions', 'id')
                ->where('label_id', $currentLabel->id())->where('type', TransactionType::Expense->value)->whereNull('release_id')],
        ]);

        $receipt->update(['transaction_id' => $validated['transaction_id'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $receipt->transaction_id === null
            ? __('Receipt taken off the cost. It waits to be booked again.')
            : __('Receipt attached to the cost.')]);

        return back();
    }

    /**
     * Have the receipt read again.
     */
    public function read(Receipt $receipt, CurrentLabel $currentLabel): RedirectResponse
    {
        if (! AiConnection::isConfigured($currentLabel->get() ?? abort(404))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Receipt reading is not set up. Choose an AI service under Settings → Connections.')]);

            return back();
        }

        $receipt->update(['status' => Receipt::READING, 'error' => null]);
        ReadReceipt::dispatch($receipt->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The receipt is being read.')]);

        return back();
    }

    public function destroy(Receipt $receipt): RedirectResponse
    {
        Storage::disk($receipt->disk)->delete($receipt->path);
        $receipt->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Receipt deleted.')]);

        return back();
    }

    /**
     * The cost a read receipt most likely belongs to: one without a
     * receipt, of the same amount, paid within ten days of its date.
     *
     * @param  iterable<Transaction>  $costs  Costs that have no receipt yet.
     */
    public static function match(Receipt $receipt, iterable $costs): ?int
    {
        if ($receipt->amount === null) {
            return null;
        }

        foreach ($costs as $cost) {
            $sameAmount = round(abs((float) $cost->total), 2) === round((float) $receipt->amount, 2)
                && ($receipt->currency === null || $receipt->currency === $cost->currency);

            if ($sameAmount && ($receipt->paid_on === null || abs($receipt->paid_on->diffInDays($cost->paid_timestamp)) <= 10)) {
                return $cost->id;
            }
        }

        return null;
    }
}
