<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutRequest;
use App\Http\Requests\PeriodRequest;
use App\Models\Payee;
use App\Models\Period;
use App\Models\Statement;
use App\Services\Royalties\PayoutService;
use App\Services\Royalties\RoyaltyCalculator;
use App\Services\Royalties\StatementGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('periods/index', [
            'periods' => Period::query()
                ->withCount(['payouts', 'statements' => fn ($query) => $query->where('archived', false)])
                ->withSum('payouts as paid', 'total')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (Period $period): array => [
                    ...$period->only(['id', 'name', 'payouts_count', 'statements_count']),
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                    'paid' => (string) ($period->paid ?? '0'),
                    'closed' => $period->closed_at !== null,
                ]),
            'suggestion' => $this->suggestion(),
        ]);
    }

    public function store(PeriodRequest $request): RedirectResponse
    {
        $this->ensureNoOverlap($request);

        $period = Period::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Period created.')]);

        return to_route('periods.show', $period);
    }

    public function show(Period $period, RoyaltyCalculator $calculator): Response
    {
        $report = $calculator->forPeriod($period);
        $statements = Statement::query()->where('period_id', $period->id)->where('archived', false)->get()->keyBy('payee_id');

        return Inertia::render('periods/show', [
            'period' => [...$report['period'], 'closed' => $period->closed_at !== null],
            'currency' => $report['currency'],
            'totals' => $report['totals'],
            'audit' => $report['audit'],
            'payees' => collect($report['payees'])->map(fn (array $payee): array => [
                'payee_id' => $payee['payee_id'],
                'payee_name' => $payee['payee_name'],
                'totals' => $payee['totals'],
                'unrecouped' => collect($payee['ledgers'])->contains('unrecouped', true),
                'statement' => isset($statements[$payee['payee_id']]) ? [
                    'id' => $statements[$payee['payee_id']]->id,
                    'published' => $statements[$payee['payee_id']]->published_at !== null,
                    'created_at' => $statements[$payee['payee_id']]->created_at->toDateTimeString(),
                ] : null,
            ]),
        ]);
    }

    public function update(PeriodRequest $request, Period $period): RedirectResponse
    {
        $this->ensureNoOverlap($request, $period);

        $period->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Period saved.')]);

        return back();
    }

    public function destroy(Period $period): RedirectResponse
    {
        if ($period->payouts()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This period has payouts and cannot be deleted.')]);

            return back();
        }

        $period->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Period deleted.')]);

        return to_route('periods.index');
    }

    /**
     * The full earnings breakdown of one payee for the period.
     */
    public function payee(Period $period, Payee $payee, RoyaltyCalculator $calculator): Response
    {
        $report = $calculator->forPeriod($period, $payee->id);

        return Inertia::render('periods/payee', [
            'period' => $report['period'],
            'payee' => $payee->only(['id', 'name']),
            'statement' => isset($report['payees'][0]) ? [
                'period' => $report['period'],
                'currency' => $report['currency'],
                ...$report['payees'][0],
            ] : null,
            'currency' => $report['currency'],
        ]);
    }

    /**
     * Generate statements for all payees, or one payee.
     */
    public function statements(Request $request, Period $period, StatementGenerator $generator): RedirectResponse
    {
        $payeeId = $request->integer('payee_id') ?: null;
        $statements = $generator->generate($period, $payeeId ? [$payeeId] : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count statement generated.|:count statements generated.', $statements->count())]);

        return back();
    }

    public function payout(PayoutRequest $request, Period $period, PayoutService $payouts): RedirectResponse
    {
        $payouts->record(
            $period,
            Payee::query()->findOrFail($request->validated('payee_id')),
            (string) $request->validated('total'),
            $request->validated('paid_on'),
            $request->validated('memo'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout recorded.')]);

        return back();
    }

    private function ensureNoOverlap(PeriodRequest $request, ?Period $except = null): void
    {
        $overlapping = Period::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where('starts_on', '<=', $request->validated('ends_on'))
            ->where('ends_on', '>=', $request->validated('starts_on'))
            ->first();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'starts_on' => "These dates overlap with the period \"{$overlapping->name}\". A transaction may only count in one period.",
            ]);
        }
    }

    /**
     * Propose the quarter after the latest period, or the last full quarter.
     *
     * @return array{name: string, starts_on: string, ends_on: string}
     */
    private function suggestion(): array
    {
        $latest = Period::query()->orderByDesc('ends_on')->first();
        $start = $latest ? $latest->ends_on->addDay()->startOfQuarter() : now()->subQuarter()->startOfQuarter();

        return [
            'name' => $start->year.' Q'.$start->quarter,
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->endOfQuarter()->toDateString(),
        ];
    }
}
