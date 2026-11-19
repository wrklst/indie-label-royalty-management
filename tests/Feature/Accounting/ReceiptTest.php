<?php

use App\Ai\Agents\ReceiptReader;
use App\Jobs\ReadReceipt;
use App\Models\Label;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Services\Receipts\ReceiptReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A label cost as the label costs page books it.
 */
function labelCost(Label $label, array $attributes = []): Transaction
{
    return Transaction::factory()->create([
        'label_id' => $label->id, 'release_id' => null, 'track_id' => null, 'type' => 'expense', 'recoupable' => false,
        'currency' => 'EUR', 'total' => '-49.90', 'converted_total' => '-49.90', 'paid_timestamp' => '2026-03-15', 'source' => 'Example Hosting', ...$attributes,
    ]);
}

function readingLabel(): Label
{
    return Label::factory()->create(['currency' => 'EUR', 'ai_provider' => 'anthropic', 'ai_key' => 'example-key']);
}

test('an uploaded receipt is stored for the label and waits to be booked', function () {
    Queue::fake();
    $label = Label::factory()->create();

    $this->actingAs(labelAdmin($label))
        ->post(route('receipts.store'), ['file' => UploadedFile::fake()->create('example-invoice.pdf', 120, 'application/pdf')])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Receipt uploaded.');

    $receipt = Receipt::query()->sole();

    expect($receipt->only(['label_id', 'original_name', 'mime_type', 'status', 'transaction_id']))->toBe([
        'label_id' => $label->id, 'original_name' => 'example-invoice.pdf', 'mime_type' => 'application/pdf', 'status' => 'new', 'transaction_id' => null,
    ])->and($receipt->path)->toStartWith("receipts/{$label->id}/");
    Storage::disk('local')->assertExists($receipt->path);
    Queue::assertNothingPushed();
});

test('a label with an AI service has uploaded receipts read', function () {
    Queue::fake();
    $label = readingLabel();

    $this->actingAs(labelAdmin($label))
        ->post(route('receipts.store'), ['file' => UploadedFile::fake()->image('till-receipt.jpg')])
        ->assertInertiaFlash('toast.message', 'Receipt uploaded. It is being read.');

    $receipt = Receipt::query()->sole();

    expect($receipt->status)->toBe('reading');
    Queue::assertPushed(ReadReceipt::class, fn (ReadReceipt $job): bool => $job->receiptId === $receipt->id);
});

test('a file that is no receipt is refused', function (UploadedFile $file) {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->post(route('receipts.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(Receipt::query()->count())->toBe(0);
})->with([
    'a spreadsheet' => [fn () => UploadedFile::fake()->create('costs.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
    'a script' => [fn () => UploadedFile::fake()->create('receipt.php', 1, 'text/x-php')],
    'a file above 20 MB' => [fn () => UploadedFile::fake()->create('scan.pdf', 20481, 'application/pdf')],
]);

test('what is read off an image receipt is stored as a proposal', function () {
    ReceiptReader::fake([[
        'vendor' => 'Example Hosting GmbH', 'date' => '2026-03-14', 'total' => '49.90', 'currency' => 'eur', 'category' => 'Hosting', 'summary' => 'Web hosting, March',
    ]]);
    $label = readingLabel();
    Storage::disk('local')->put('receipts/example.jpg', UploadedFile::fake()->image('receipt.jpg')->getContent());
    $receipt = Receipt::factory()->create(['label_id' => $label->id, 'path' => 'receipts/example.jpg', 'mime_type' => 'image/jpeg', 'status' => 'reading']);

    app(ReceiptReading::class)->read($receipt);

    expect($receipt->refresh()->only(['status', 'vendor', 'currency', 'category', 'summary', 'error']))->toBe([
        'status' => 'read', 'vendor' => 'Example Hosting GmbH', 'currency' => 'EUR', 'category' => 'Hosting', 'summary' => 'Web hosting, March', 'error' => null,
    ])
        ->and($receipt->paid_on->toDateString())->toBe('2026-03-14')
        ->and((float) $receipt->amount)->toBe(49.9);
    ReceiptReader::assertPrompted(fn ($prompt): bool => $prompt->attachments->count() === 1);
});

test('a PDF receipt is read from its text', function () {
    Process::fake(['*pdftotext*' => Process::result("Example Hosting GmbH\nInvoice 2026-03-14\nTotal 49.90 EUR")]);
    ReceiptReader::fake([['vendor' => 'Example Hosting GmbH', 'date' => '2026-03-14', 'total' => '49.90', 'currency' => 'EUR', 'category' => 'Hosting', 'summary' => 'Web hosting']]);
    $label = readingLabel();
    Storage::disk('local')->put('receipts/example.pdf', '%PDF-1.4 example');
    $receipt = Receipt::factory()->create(['label_id' => $label->id, 'path' => 'receipts/example.pdf']);

    app(ReceiptReading::class)->read($receipt);

    expect($receipt->refresh()->status)->toBe('read');
    ReceiptReader::assertPrompted(fn ($prompt): bool => $prompt->contains('Total 49.90 EUR') && $prompt->attachments->isEmpty());
});

test('what the service makes up is not stored', function () {
    ReceiptReader::fake([['vendor' => '', 'date' => 'last Tuesday', 'total' => 'n/a', 'currency' => 'Euro', 'category' => 'Yachts', 'summary' => '']]);
    $label = readingLabel();
    Storage::disk('local')->put('receipts/example.jpg', UploadedFile::fake()->image('receipt.jpg')->getContent());
    $receipt = Receipt::factory()->create(['label_id' => $label->id, 'path' => 'receipts/example.jpg', 'mime_type' => 'image/jpeg']);

    app(ReceiptReading::class)->read($receipt);

    expect($receipt->refresh()->only(['status', 'vendor', 'paid_on', 'amount', 'currency', 'category', 'summary']))->toBe([
        'status' => 'read', 'vendor' => null, 'paid_on' => null, 'amount' => null, 'currency' => null, 'category' => null, 'summary' => null,
    ]);
});

test('a receipt that cannot be read says why and can be booked by hand', function () {
    $receipt = Receipt::factory()->create(['status' => 'reading']);

    (new ReadReceipt($receipt->id))->failed(new RuntimeException('The AI service could not be reached.'));

    expect($receipt->refresh()->only(['status', 'error']))->toBe(['status' => 'failed', 'error' => 'The AI service could not be reached.']);
});

test('the label costs page lists receipts that wait and proposes the cost a read one belongs to', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $matching = labelCost($label);
    labelCost($label, ['total' => '-12', 'converted_total' => '-12']);
    $attached = labelCost($label, ['paid_timestamp' => '2026-02-01']);
    Receipt::factory()->create(['label_id' => $label->id, 'transaction_id' => $attached->id]);
    $waiting = Receipt::factory()->create([
        'label_id' => $label->id, 'status' => 'read', 'vendor' => 'Example Hosting GmbH', 'amount' => '49.90', 'currency' => 'EUR', 'paid_on' => '2026-03-14',
    ]);
    Receipt::factory()->create();

    $this->actingAs(labelAdmin($label))->get(route('label-costs.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('receiptReading', false)
            ->has('receipts', 1)
            ->where('receipts.0.id', $waiting->id)
            ->where('receipts.0.amount', '49.90')
            ->where('receipts.0.suggested_cost_id', $matching->id)
            ->where('costs', fn ($costs) => collect($costs)->firstWhere('id', $attached->id)['receipt_id'] !== null
                && collect($costs)->firstWhere('id', $matching->id)['receipt_id'] === null));
});

test('a receipt is booked as a new cost and attached to it', function () {
    $label = Label::factory()->create(['currency' => 'EUR']);
    $receipt = Receipt::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->post(route('label-costs.store'), ['name' => 'Example Hosting GmbH', 'amount' => '49.90', 'currency' => 'EUR', 'category' => 'Hosting', 'paid_on' => '2026-03-14', 'receipt_id' => $receipt->id])
        ->assertSessionHasNoErrors();

    $cost = Transaction::query()->sole();

    expect($receipt->refresh()->transaction_id)->toBe($cost->id)
        ->and((float) $cost->total)->toBe(-49.9);
});

test('a receipt is attached to an existing cost and taken off it again', function () {
    $label = Label::factory()->create();
    $cost = labelCost($label);
    $receipt = Receipt::factory()->create(['label_id' => $label->id]);
    $this->actingAs(labelAdmin($label));

    $this->put(route('receipts.update', $receipt), ['transaction_id' => $cost->id])->assertSessionHasNoErrors();
    expect($receipt->refresh()->transaction_id)->toBe($cost->id);

    $this->put(route('receipts.update', $receipt), ['transaction_id' => ''])->assertSessionHasNoErrors();
    expect($receipt->refresh()->transaction_id)->toBeNull();
});

test('a receipt cannot be attached to something that is no cost of the label', function (Closure $target) {
    $label = Label::factory()->create();
    $receipt = Receipt::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))
        ->put(route('receipts.update', $receipt), ['transaction_id' => $target($label)->id])
        ->assertSessionHasErrors('transaction_id');

    expect($receipt->refresh()->transaction_id)->toBeNull();
})->with([
    'a cost of another label' => [fn (Label $label) => labelCost(Label::factory()->create())],
    'income' => [fn (Label $label) => labelCost($label, ['type' => 'income', 'total' => '10', 'converted_total' => '10'])],
]);

test('deleting a cost frees its receipt, and deleting a receipt removes its file', function () {
    $label = Label::factory()->create();
    $cost = labelCost($label);
    Storage::disk('local')->put('receipts/example.pdf', 'example');
    $receipt = Receipt::factory()->create(['label_id' => $label->id, 'transaction_id' => $cost->id]);
    $this->actingAs(labelAdmin($label));

    $cost->delete();
    expect($receipt->refresh()->transaction_id)->toBeNull();

    $this->delete(route('receipts.destroy', $receipt))->assertSessionHasNoErrors();

    expect(Receipt::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('receipts/example.pdf');
});

test('a receipt is shown to admins of its label only', function () {
    $label = Label::factory()->create();
    Storage::disk('local')->put('receipts/example.pdf', '%PDF-1.4 example');
    $receipt = Receipt::factory()->create(['label_id' => $label->id]);

    $this->actingAs(labelAdmin($label))->get(route('receipts.show', $receipt))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('receipts.show', $receipt))->assertNotFound();
    $this->actingAs(labelAdmin(Label::factory()->create()))->delete(route('receipts.destroy', $receipt))->assertNotFound();
    Storage::disk('local')->assertExists('receipts/example.pdf');
});

test('reading again needs an AI service', function () {
    Queue::fake();
    $label = Label::factory()->create();
    $receipt = Receipt::factory()->create(['label_id' => $label->id, 'status' => 'failed', 'error' => 'Example error']);
    $this->actingAs(labelAdmin($label));

    $this->post(route('receipts.read', $receipt))->assertInertiaFlash('toast.type', 'error');
    Queue::assertNothingPushed();

    $label->update(['ai_provider' => 'anthropic', 'ai_key' => 'example-key']);

    $this->post(route('receipts.read', $receipt))->assertInertiaFlash('toast.message', 'The receipt is being read.');
    expect($receipt->refresh()->only(['status', 'error']))->toBe(['status' => 'reading', 'error' => null]);
    Queue::assertPushed(ReadReceipt::class);
});
