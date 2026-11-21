<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayeeRequest;
use App\Models\ContractSplit;
use App\Models\Payee;
use App\Models\Payout;
use App\Models\Period;
use App\Models\Statement;
use App\Models\Transaction;
use App\Services\Royalties\RoyaltyCalculator;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    public function index(Request $request): Response
    {
        $sorting = Sorting::for($request, [
            'name' => 'name',
            'email' => Sorting::nullsLast('email'),
            'splits_count' => 'splits_count',
            'statements_count' => 'statements_count',
            'payouts_count' => 'payouts_count',
        ], fn (Builder $query) => $query->orderBy('name'));

        $payees = Payee::query()
            ->withCount(['splits', 'payouts', 'statements'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->tap($sorting)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Payee $payee): array => $payee->only(['id', 'name', 'email', 'tags', 'notes', 'splits_count', 'payouts_count', 'statements_count']));

        return Inertia::render('payees/index', [
            'payees' => $payees,
            'filters' => ['search' => $request->string('search')->value(), ...$sorting->toArray()],
        ]);
    }

    public function show(Payee $payee, RoyaltyCalculator $calculator): Response
    {
        $latestPeriod = Period::query()->orderByDesc('ends_on')->first();
        $summary = $latestPeriod ? ($calculator->forPeriod($latestPeriod, $payee->id)['payees'][0] ?? null) : null;

        return Inertia::render('payees/show', [
            'payee' => $payee->only(['id', 'name', 'email', 'tags', 'notes']),
            'period' => $latestPeriod?->only(['id', 'name']),
            'summary' => $summary ? ['totals' => $summary['totals'], 'ledgers' => collect($summary['ledgers'])->map(fn (array $ledger): array => collect($ledger)->except('releases')->all())->all()] : null,
            'splits' => ContractSplit::query()
                ->where('payee_id', $payee->id)
                ->with(['contract:id,name', 'ledger:id,name', 'territory:id,name', 'contract.releases:id,name,contract_id', 'contract.tracks:id,name,contract_id'])
                ->get()
                ->map(fn (ContractSplit $split): array => [
                    'id' => $split->id,
                    'contract_id' => $split->contract_id,
                    'contract' => $split->contract?->name,
                    'split_amount' => $split->split_amount,
                    'ledger' => $split->ledger?->name,
                    'territory' => $split->territory?->name,
                    'used_by' => [...($split->contract?->releases->pluck('name')->all() ?? []), ...($split->contract?->tracks->pluck('name')->all() ?? [])],
                    'used_by_links' => [
                        ...($split->contract?->releases->map(fn ($release): array => ['to' => 'release', 'id' => $release->id, 'name' => $release->name])->all() ?? []),
                        ...($split->contract?->tracks->map(fn ($track): array => ['to' => 'track', 'id' => $track->id, 'name' => $track->name])->all() ?? []),
                    ],
                ]),
            'payouts' => Payout::query()->where('payee_id', $payee->id)->with('period:id,name')->orderByDesc('paid_on')->get()
                ->map(fn (Payout $payout): array => [
                    'id' => $payout->id, 'total' => $payout->total, 'paid_on' => $payout->paid_on->toDateString(),
                    'period' => $payout->period?->name, 'memo' => $payout->memo,
                ]),
            'statements' => Statement::query()->where('payee_id', $payee->id)->where('archived', false)->with('period:id,name')->latest('id')->get()
                ->map(fn (Statement $statement): array => [
                    'id' => $statement->id, 'name' => $statement->name, 'period' => $statement->period?->name,
                    'payable' => $statement->payable, 'published' => $statement->published_at !== null,
                ]),
            'transfers' => Transaction::query()->where('type', 'transfer')->where('payee_id', $payee->id)
                ->with(['release:id,name', 'ledger:id,name'])->orderByDesc('paid_timestamp')->limit(200)->get()
                ->map(fn (Transaction $transfer): array => [
                    'id' => $transfer->id, 'paid_timestamp' => $transfer->paid_timestamp->toDateString(), 'total' => $transfer->total,
                    'converted_total' => $transfer->converted_total, 'currency' => $transfer->currency, 'category' => $transfer->category,
                    'memo' => $transfer->memo, 'release' => $transfer->release?->name, 'release_id' => $transfer->release_id, 'ledger' => $transfer->ledger?->name,
                ]),
        ]);
    }

    public function store(PayeeRequest $request): RedirectResponse
    {
        Payee::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payee created.')]);

        return back();
    }

    public function update(PayeeRequest $request, Payee $payee): RedirectResponse
    {
        $payee->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payee saved.')]);

        return back();
    }

    public function destroy(Payee $payee): RedirectResponse
    {
        $hasTransfers = Transaction::query()->where('payee_id', $payee->id)->exists();

        if ($hasTransfers || $payee->payouts()->exists() || $payee->statements()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This payee has transfers, payouts or statements and cannot be deleted.')]);

            return back();
        }

        // A member linked to the payee goes back to being unlinked, not to an empty portal.
        DB::table('label_user')->where('payee_id', $payee->id)->update(['payee_id' => null]);
        $payee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payee deleted.')]);

        return to_route('payees.index');
    }
}
