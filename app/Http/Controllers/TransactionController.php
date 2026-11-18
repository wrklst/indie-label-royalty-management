<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Track;
use App\Models\Transaction;
use App\Support\CurrentLabel;
use App\Support\Decimal;
use App\Support\Formats;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $this->filtered($request);

        $summary = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as transactions, SUM(converted_total) as total, SUM(quantity) as quantity')
            ->first();

        $transactions = $query
            ->with(['release:id,name,catalog_number', 'track:id,name', 'payee:id,name'])
            ->orderByDesc('timestamp')->orderByDesc('id')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (Transaction $transaction): array => [
                ...$transaction->only(['id', 'total', 'converted_total', 'currency', 'quantity', 'category', 'format', 'region', 'retailer', 'memo', 'published', 'data_import_id']),
                'type' => $transaction->type->value,
                'timestamp' => $transaction->timestamp->toDateString(),
                'paid_timestamp' => $transaction->paid_timestamp->toDateString(),
                'release' => $transaction->release?->only(['id', 'name']),
                'track' => $transaction->track?->name,
                'payee' => $transaction->payee?->name,
            ]);

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'summary' => [
                'transactions' => (int) ($summary->transactions ?? 0),
                'total' => Decimal::parse($summary->total ?? 0) ?? '0',
                'quantity' => Decimal::parse($summary->quantity ?? 0) ?? '0',
            ],
            'filters' => $request->only(['search', 'type', 'release', 'retailer', 'import', 'from', 'to', 'date', 'unassigned', 'unconverted']),
            'releaseOptions' => Options::releases(),
            'retailerOptions' => Transaction::query()->whereNotNull('retailer')->distinct()->orderBy('retailer')->pluck('retailer'),
            'importOptions' => DataImport::query()->latest('id')->limit(100)->get(['id', 'name'])->map->only(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('transactions/form', [
            'transaction' => null,
            'defaults' => ['type' => $request->string('type', 'income')->value(), 'release_id' => $request->integer('release') ?: null, 'payee_id' => $request->integer('payee') ?: null],
            ...$this->options(),
        ]);
    }

    public function store(TransactionRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        Transaction::query()->create([
            ...$this->attributes($request, $currentLabel),
            'source' => $request->validated('source') ?? 'Manual',
            'importer_source' => 'manual',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction created.')]);

        return to_route('transactions.index');
    }

    public function edit(Transaction $transaction): Response
    {
        return Inertia::render('transactions/form', [
            'transaction' => [
                ...$transaction->only(['id', 'total', 'currency', 'conversion_rate', 'converted_total', 'quantity', 'price', 'release_id', 'track_id', 'contract_id', 'payee_id', 'ledger_id', 'category', 'format', 'usage_type', 'region', 'retailer', 'provider', 'external_id', 'source', 'memo', 'published', 'billable', 'raw_data', 'data_import_id']),
                'type' => $transaction->type->value,
                'timestamp' => $transaction->timestamp->toDateString(),
                'paid_timestamp' => $transaction->paid_timestamp->toDateString(),
            ],
            'defaults' => null,
            ...$this->options(),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction, CurrentLabel $currentLabel): RedirectResponse
    {
        $transaction->update($this->attributes($request, $currentLabel));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction saved.')]);

        return to_route('transactions.index');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction deleted.')]);

        return back();
    }

    /**
     * @return Builder<Transaction>
     */
    private function filtered(Request $request): Builder
    {
        $dateColumn = $request->input('date') === 'paid' ? 'paid_timestamp' : 'timestamp';

        return Transaction::query()
            ->when(TransactionType::tryFrom((string) $request->input('type')), fn ($query, TransactionType $type) => $query->where('type', $type))
            ->when($request->integer('release'), fn ($query, int $release) => $query->where('release_id', $release))
            ->when($request->integer('import'), fn ($query, int $import) => $query->where('data_import_id', $import))
            ->when($request->string('retailer')->value(), fn ($query, string $retailer) => $query->where('retailer', $retailer))
            ->when($request->date('from'), fn ($query, $from) => $query->where($dateColumn, '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($query, $to) => $query->where($dateColumn, '<=', $to->toDateString()))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('release_id')->where('type', '<>', 'transfer'))
            ->when($request->boolean('unconverted'), fn ($query) => $query->whereNull('converted_total'))
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('memo', 'like', "%{$search}%")
                ->orWhere('retailer', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('external_id', $search)
                ->orWhereHas('track', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('isrc', $search))
                ->orWhereHas('release', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('catalog_number', 'like', "%{$search}%"))));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TransactionRequest $request, CurrentLabel $currentLabel): array
    {
        $attributes = $request->validated();
        $label = $currentLabel->get();

        if (! empty($attributes['track_id'])) {
            $attributes['release_id'] = Track::query()->whereKey($attributes['track_id'])->firstOrFail()->release_id;
        }

        $attributes['item_id'] = empty($attributes['release_id']) ? null : Item::query()
            ->where('release_id', $attributes['release_id'])
            ->where('is_additionals', true)
            ->value('id');

        $rate = $attributes['currency'] === $label->currency ? '1' : Decimal::parse($attributes['conversion_rate'] ?? null);
        $total = Decimal::parse($attributes['total']);

        return [
            ...$attributes,
            'total' => $total,
            'conversion_rate' => $rate,
            'converted_total' => $rate === null ? null : Decimal::mul($total, $rate),
            'format' => Formats::canonical($attributes['format'] ?? null),
            'region' => isset($attributes['region']) ? strtoupper($attributes['region']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'releaseOptions' => Options::releases(),
            'trackOptions' => Options::tracks(),
            'contractOptions' => Options::contracts(),
            'payeeOptions' => Options::payees(),
            'ledgerOptions' => Options::ledgers(),
            'categories' => config('royalties.categories'),
            'formats' => config('royalties.formats'),
        ];
    }
}
