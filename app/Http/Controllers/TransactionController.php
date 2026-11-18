<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Imports\Identifiers;
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
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'retailer' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'date' => ['nullable', 'string'],
        ]);

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
                ...$transaction->only(['id', 'total', 'converted_total', 'currency', 'quantity', 'category', 'format', 'region', 'retailer', 'memo', 'published', 'recoupable', 'data_import_id']),
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
            'defaults' => [
                'type' => (TransactionType::tryFrom((string) $request->query('type')) ?? TransactionType::Income)->value,
                'release_id' => $request->integer('release') ?: null,
                'payee_id' => $request->integer('payee') ?: null,
                'recoupable' => $request->boolean('recoupable', true),
            ],
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
                ...$transaction->only(['id', 'total', 'currency', 'conversion_rate', 'converted_total', 'quantity', 'price', 'release_id', 'track_id', 'contract_id', 'payee_id', 'ledger_id', 'category', 'format', 'usage_type', 'region', 'retailer', 'provider', 'external_id', 'source', 'memo', 'published', 'billable', 'recoupable', 'raw_data', 'data_import_id']),
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
        $transaction->update($this->attributes($request, $currentLabel, $transaction));

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
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                // Typed text is matched literally: % and _ are not wildcards.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $isrc = Identifiers::isrc($search);

                return $query->where(fn ($query) => $query
                    ->whereRaw("memo LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("retailer LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("category LIKE ? ESCAPE '!'", [$like])
                    ->orWhere('external_id', $search)
                    ->orWhereHas('track', fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])->when($isrc, fn ($query) => $query->orWhere('isrc', $isrc)))
                    ->orWhereHas('release', fn ($query) => $query->where(fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("catalog_number LIKE ? ESCAPE '!'", [$like]))));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TransactionRequest $request, CurrentLabel $currentLabel, ?Transaction $transaction = null): array
    {
        $attributes = $request->validated();
        $label = $currentLabel->get();

        if (! empty($attributes['track_id'])) {
            $attributes['release_id'] = Track::query()->whereKey($attributes['track_id'])->firstOrFail()->release_id;
        }

        // An imported transaction keeps the item it was booked on; a new one,
        // or one moved to another release or track, goes on "[Additionals]".
        $releaseId = $attributes['release_id'] ?? null;
        $trackId = $attributes['track_id'] ?? null;

        if ($transaction === null || $transaction->release_id !== $releaseId || $transaction->track_id !== $trackId) {
            $attributes['item_id'] = $releaseId === null ? null : Item::query()
                ->where('release_id', $releaseId)
                ->where('is_additionals', true)
                ->value('id');
        }

        $rate = $attributes['currency'] === $label->currency ? '1' : Decimal::parse($attributes['conversion_rate'] ?? null);
        $total = Decimal::parse($attributes['total']);

        if (array_key_exists('format', $attributes)) {
            $attributes['format'] = Formats::canonical($attributes['format']);
        }

        if (isset($attributes['region'])) {
            $attributes['region'] = strtoupper($attributes['region']);
        }

        // Only an expense can be borne by the label alone.
        $recoupable = $attributes['type'] !== TransactionType::Expense->value
            || ($attributes['recoupable'] ?? $transaction->recoupable ?? true);

        return [
            ...$attributes,
            'recoupable' => $recoupable,
            'total' => $total,
            'conversion_rate' => $rate,
            'converted_total' => $rate === null ? null : Decimal::mul($total, $rate),
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
