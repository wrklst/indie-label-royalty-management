<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Period;
use App\Models\Statement;
use App\Support\CurrentLabel;
use App\Support\Decimal;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a payee sees: their own published statements, an overview taken
 * from the latest of them and the payouts they received. Figures the
 * label has not published never reach this controller's responses.
 */
class PortalController extends Controller
{
    public function __construct(private readonly CurrentLabel $currentLabel) {}

    public function index(Request $request): Response
    {
        $statementSorting = Sorting::for($request, [
            'period' => Sorting::related(Period::class, 'period_id'),
            'published_at' => 'published_at',
            'balance' => 'balance',
            'payable' => 'payable',
        ], fn (Builder $query) => $query->latest('id'), 'statements');
        $payoutSorting = Sorting::for($request, [
            'paid_on' => 'paid_on',
            'period' => Sorting::related(Period::class, 'period_id'),
            'memo' => Sorting::nullsLast('memo'),
            'total' => 'total',
        ], fn (Builder $query) => $query->orderByDesc('paid_on')->orderByDesc('id'), 'payouts');
        $isAdmin = $this->currentLabel->isAdmin();
        $payeeId = $isAdmin ? null : $this->currentLabel->payeeId();

        return Inertia::render('portal/index', [
            'view' => match (true) {
                $isAdmin => 'admin',
                $payeeId === null => 'unlinked',
                default => 'payee',
            },
            'overview' => $payeeId === null ? null : $this->overview($payeeId),
            'payouts' => $this->payouts($payeeId ?? 0, $payoutSorting),
            'statements' => $this->visible()->with('period:id,name,starts_on,ends_on')->tap($statementSorting)
                ->paginate(25, pageName: 'statements_page')->withQueryString()
                ->through(fn (Statement $statement): array => [
                    ...$statement->only(['id', 'name', 'balance', 'payable']),
                    'period' => $statement->period?->name,
                    'published_at' => $statement->published_at?->toDateString(),
                ]),
            'filters' => [...$statementSorting->toArray(), ...$payoutSorting->toArray()],
        ]);
    }

    public function show(int $statement): Response
    {
        $statement = $this->visible()->findOrFail($statement);

        return Inertia::render('statements/show', [
            'statement' => [
                ...$statement->only(['id', 'name', 'archived', 'revision_of_id', 'period_id', 'payee_id']),
                'published' => true,
                'created_at' => $statement->created_at->toIso8601String(),
                'data' => $statement->data,
            ],
            'portal' => true,
        ]);
    }

    public function download(int $statement): StreamedResponse
    {
        return StatementController::csv($this->visible()->findOrFail($statement));
    }

    /**
     * Where the payee stands according to the statement published for
     * their most recent period. Live figures are deliberately not used:
     * they may contain imports and corrections the label has not released.
     *
     * @return array{
     *     statement_id: int,
     *     period: string,
     *     published_at: string|null,
     *     currency: string|null,
     *     owed: string,
     *     earned: string,
     *     paid: string,
     *     paid_since: string,
     *     ledgers: list<array{name: string, balance: string, unrecouped: bool}>
     * }|null
     */
    private function overview(int $payeeId): ?array
    {
        $statement = $this->visible()
            ->where('payee_id', $payeeId)
            ->with('period:id,name')
            ->orderByDesc(Period::query()->select('ends_on')->whereColumn('periods.id', 'statements.period_id'))
            ->orderByDesc('id')
            ->first();

        if ($statement === null) {
            return null;
        }

        $ledgers = [];

        foreach (Arr::array($statement->data, 'ledgers', []) as $ledger) {
            $balance = (string) data_get($ledger, 'balance', '0');

            $ledgers[] = [
                'name' => (string) data_get($ledger, 'ledger_name', 'Primary'),
                'balance' => $balance,
                'unrecouped' => Decimal::cmp($balance, '0') < 0,
            ];
        }

        $currency = data_get($statement->data, 'currency');

        return [
            'statement_id' => $statement->id,
            'period' => (string) data_get($statement->data, 'period.name', $statement->period->name ?? $statement->name),
            'published_at' => $statement->published_at?->toDateString(),
            'currency' => is_string($currency) ? $currency : null,
            'owed' => (string) data_get($statement->data, 'totals.outstanding', $statement->payable),
            'earned' => (string) data_get($statement->data, 'totals.payee_share', '0'),
            'paid' => (string) data_get($statement->data, 'totals.total_paid', '0'),
            'paid_since' => Decimal::add('0', (string) Payout::query()
                ->where('payee_id', $payeeId)
                ->where('created_at', '>', $statement->created_at)
                ->sum('total')),
            'ledgers' => $ledgers,
        ];
    }

    /**
     * Every payment the label recorded for the payee. Payouts are facts,
     * so they show whether or not a statement mentions them yet.
     *
     * @return LengthAwarePaginator<int, array{id: int, paid_on: string, period: string|null, total: string, memo: string|null}>
     */
    private function payouts(int $payeeId, Sorting $sorting): LengthAwarePaginator
    {
        return Payout::query()
            ->where('payee_id', $payeeId)
            ->with('period:id,name')
            ->tap($sorting)
            ->paginate(25, pageName: 'payouts_page')->withQueryString()
            ->through(fn (Payout $payout): array => [
                'id' => $payout->id,
                'paid_on' => $payout->paid_on->toDateString(),
                'period' => $payout->period?->name,
                'total' => $payout->total,
                'memo' => $payout->memo,
            ]);
    }

    /**
     * @return Builder<Statement>
     */
    private function visible(): Builder
    {
        return Statement::query()
            ->where('archived', false)
            ->whereNotNull('published_at')
            ->when(
                ! $this->currentLabel->isAdmin(),
                fn (Builder $query) => $query->where('payee_id', $this->currentLabel->payeeId() ?? 0),
            );
    }
}
