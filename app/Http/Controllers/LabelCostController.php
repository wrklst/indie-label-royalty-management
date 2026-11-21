<?php

namespace App\Http\Controllers;

use App\Enums\RecurrenceInterval;
use App\Enums\TransactionType;
use App\Models\Label;
use App\Models\Receipt;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use App\Services\Receipts\AiConnection;
use App\Services\Royalties\ExchangeRates;
use App\Services\Royalties\RecurringExpenseBooker;
use App\Support\CurrentLabel;
use App\Support\Decimal;
use App\Support\Filters;
use App\Support\PageSize;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

/**
 * Running costs of the label itself: subscriptions, admin, anything not
 * tied to a release. They are expenses the label bears alone, so they
 * lower its result in the profitability report and never touch royalties.
 */
class LabelCostController extends Controller
{
    /** The most costs the receipt dialog offers to attach a receipt to. */
    private const int ATTACHABLE_COSTS = 200;

    public function __construct(private readonly CurrentLabel $currentLabel, private readonly RecurringExpenseBooker $booker, private readonly ExchangeRates $rates) {}

    public function index(Request $request): Response
    {
        $label = $this->label();
        $this->booker->bookDue($label);

        $costs = fn () => Transaction::query()->where('type', TransactionType::Expense)->where('recoupable', false)->whereNull('release_id');
        $year = now()->year;
        $search = trim(Filters::text($request, 'search'));
        $costSorting = Sorting::for($request, [
            'paid_timestamp' => 'paid_timestamp',
            'name' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression('COALESCE(source, memo)'), $direction),
            'category' => Sorting::nullsLast('category'),
            'amount' => Sorting::nullsLast('converted_total'),
        ], fn (Builder $query) => $query->orderByDesc('paid_timestamp')->orderByDesc('id'), 'costs');
        $recurringSorting = Sorting::for($request, [
            'name' => 'name',
            'category' => Sorting::nullsLast('category'),
            'amount' => 'amount',
            'starts_on' => 'starts_on',
            'next_due_on' => fn (Builder $query, SortDirection $direction) => $query->orderBy($query->getGrammar() instanceof SQLiteGrammar
                ? new Expression("date(starts_on, '+' || (occurrences_booked * CASE \"interval\" WHEN 'monthly' THEN 1 WHEN 'quarterly' THEN 3 ELSE 12 END) || ' months')")
                : new Expression("DATE_ADD(starts_on, INTERVAL occurrences_booked * CASE `interval` WHEN 'monthly' THEN 1 WHEN 'quarterly' THEN 3 ELSE 12 END MONTH)"), $direction),
            'occurrences_booked' => 'occurrences_booked',
        ], fn (Builder $query) => $query->orderBy('name'), 'recurring');

        $listed = $costs()
            ->when($search, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('source', 'like', "%{$search}%")->orWhere('memo', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")))
            ->tap($costSorting)
            ->paginate(PageSize::MAIN, pageName: 'costs_page')->withQueryString();
        $withReceipt = Receipt::query()->whereIn('transaction_id', $listed->getCollection()->modelKeys())->get()->keyBy('transaction_id');
        // A cost without a receipt can have one attached, whichever page of costs it is on; the newest are offered.
        $withoutReceipt = $costs()->whereNotIn('id', Receipt::query()->whereNotNull('transaction_id')->select('transaction_id'))
            ->orderByDesc('paid_timestamp')->orderByDesc('id')->limit(self::ATTACHABLE_COSTS)->get();
        [$runningYearly, $withoutRate] = $this->runningPerYear($label);

        return Inertia::render('label-costs/index', [
            'currency' => $label->currency,
            'categories' => config('royalties.categories.overhead'),
            'intervals' => array_map(fn (RecurrenceInterval $interval): array => ['value' => $interval->value, 'label' => $interval->label()], RecurrenceInterval::cases()),
            'totals' => [
                'this_year' => Decimal::parse($costs()->whereYear('paid_timestamp', $year)->sum('converted_total')) ?? '0',
                'last_year' => Decimal::parse($costs()->whereYear('paid_timestamp', $year - 1)->sum('converted_total')) ?? '0',
                'year' => $year,
                'recurring_per_year' => $runningYearly,
                'recurring_without_rate' => $withoutRate,
            ],
            'filters' => ['search' => $search, ...$costSorting->toArray(), ...$recurringSorting->toArray()],
            'recurring' => RecurringExpense::query()->tap($recurringSorting)->paginate(PageSize::SECONDARY, pageName: 'recurring_page')->withQueryString()
                ->through(fn (RecurringExpense $expense): array => [
                    ...$expense->only(['id', 'name', 'category', 'amount', 'currency', 'memo', 'occurrences_booked']),
                    'conversion_rate' => $expense->conversion_rate === null ? null : rtrim(rtrim((string) $expense->conversion_rate, '0'), '.'),
                    'interval' => $expense->interval->value,
                    'starts_on' => $expense->starts_on->toDateString(),
                    'ends_on' => $expense->ends_on?->toDateString(),
                    'next_due_on' => $expense->nextDueOn()?->toDateString(),
                ]),
            'costs' => $listed->through(fn (Transaction $cost): array => [
                ...$cost->only(['id', 'category', 'memo', 'source', 'total', 'converted_total', 'currency']),
                'conversion_rate' => $cost->conversion_rate === null || $cost->currency === $label->currency ? null : rtrim(rtrim((string) $cost->conversion_rate, '0'), '.'),
                // Typed and shown as the positive amount; a credit is stored positive.
                'amount' => Decimal::abs($cost->total),
                'refund' => Decimal::isPositive($cost->total),
                'paid_timestamp' => $cost->paid_timestamp->toDateString(),
                'recurring' => $cost->recurring_expense_id !== null,
                'receipt_id' => $withReceipt->get($cost->id)?->id,
            ]),
            'attachableCosts' => $withoutReceipt->map(fn (Transaction $cost): array => [
                ...$cost->only(['id', 'source', 'memo', 'total', 'currency']),
                'paid_timestamp' => $cost->paid_timestamp->toDateString(),
            ])->values(),
            'receiptReading' => AiConnection::isConfigured($label),
            // Whether any receipt is being read, on whichever page it is: the page polls while it is.
            'receiptsBeingRead' => Receipt::query()->whereNull('transaction_id')->where('status', 'reading')->exists(),
            // Receipts that belong to no cost yet, with what was read off them.
            'receipts' => Receipt::query()->whereNull('transaction_id')->orderByDesc('id')->paginate(PageSize::SECONDARY, pageName: 'receipts_page')->withQueryString()
                ->through(fn (Receipt $receipt): array => [
                    ...$receipt->only(['id', 'original_name', 'status', 'vendor', 'currency', 'category', 'summary', 'error']),
                    'amount' => $receipt->amount === null ? null : Decimal::round($receipt->amount, 2),
                    'paid_on' => $receipt->paid_on?->toDateString(),
                    'uploaded_on' => $receipt->created_at?->toDateString(),
                    'suggested_cost_id' => ReceiptController::match($receipt, $withoutReceipt),
                ]),
        ]);
    }

    /**
     * Book a one-time cost.
     */
    public function store(Request $request): RedirectResponse
    {
        $label = $this->label();
        $validated = $request->validate([
            ...$this->amountRules(),
            'refund' => ['boolean'],
            'paid_timestamp' => ['required', 'date'],
            'receipt_id' => ['nullable', 'integer', Rule::exists('receipts', 'id')->where('label_id', $label->id)->whereNull('transaction_id')],
        ]);

        $currency = strtoupper($validated['currency']);
        $amount = Decimal::parse($validated['amount']);
        // A cost is stored negative; a refund or credit reduces the costs and is stored positive.
        $total = ($validated['refund'] ?? false) ? $amount : Decimal::sub('0', $amount);
        $rate = $this->rates->for($currency, $label->currency, $validated['conversion_rate'] ?? null, $validated['paid_timestamp']);

        $cost = Transaction::query()->create([
            'type' => TransactionType::Expense,
            'recoupable' => false,
            'timestamp' => $validated['paid_timestamp'],
            'paid_timestamp' => $validated['paid_timestamp'],
            'total' => $total,
            'currency' => $currency,
            'conversion_rate' => $rate,
            'converted_total' => $rate === null ? null : Decimal::mul($total, $rate),
            'category' => $validated['category'] ?? null,
            'source' => $validated['name'],
            'importer_source' => 'manual',
            'memo' => $validated['memo'] ?? $validated['name'],
        ]);

        if (isset($validated['receipt_id'])) {
            Receipt::query()->whereKey($validated['receipt_id'])->update(['transaction_id' => $cost->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cost booked.')]);

        return back();
    }

    /**
     * Change a booked cost in the dialog it was booked in. Only costs of the label itself:
     * an expense without a release that the label bears alone.
     */
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->type === TransactionType::Expense && ! $transaction->recoupable && $transaction->release_id === null, 404);

        $validated = $request->validate([
            ...$this->amountRules(),
            'refund' => ['boolean'],
            'paid_timestamp' => ['required', 'date'],
        ]);

        $label = $this->label();
        $currency = strtoupper($validated['currency']);
        $amount = Decimal::parse($validated['amount']);
        $refund = $validated['refund'] ?? Decimal::isPositive($transaction->total);
        $total = $refund ? $amount : Decimal::sub('0', $amount);
        $shownName = $transaction->source ?? $transaction->memo;
        $rate = $this->rates->for($currency, $label->currency, $validated['conversion_rate'] ?? null, $validated['paid_timestamp']);

        // Only what the dialog shows is written: the sale date stays unless it was the paid date, and the supplier unless the name changed.
        $transaction->update([
            ...($transaction->timestamp->toDateString() === $transaction->paid_timestamp->toDateString() ? ['timestamp' => $validated['paid_timestamp']] : []),
            'paid_timestamp' => $validated['paid_timestamp'],
            'total' => $total,
            'currency' => $currency,
            'conversion_rate' => $rate,
            'converted_total' => $rate === null ? null : Decimal::mul($total, $rate),
            'category' => $validated['category'] ?? null,
            ...($validated['name'] !== $shownName || ($transaction->source === null && ! isset($validated['memo'])) ? ['source' => $validated['name']] : []),
            'memo' => $validated['memo'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cost saved.')]);

        return back();
    }

    public function storeRecurring(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->amountRules(),
            'interval' => ['required', Rule::enum(RecurrenceInterval::class)],
            'starts_on' => ['required', 'date', 'after:1999-12-31'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        RecurringExpense::query()->create([...$validated, 'currency' => strtoupper($validated['currency'])]);
        $booked = $this->booker->bookDue($this->label());

        Inertia::flash('toast', ['type' => 'success', 'message' => $booked > 0
            ? trans_choice('Recurring cost created and :count past payment booked.|Recurring cost created and :count past payments booked.', $booked)
            : __('Recurring cost created. It is booked on each due date.')]);

        return back();
    }

    /**
     * Change a recurring cost from now on. Payments already booked keep
     * their amounts; the rhythm and start cannot change once any exist.
     */
    public function updateRecurring(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->amountRules(),
            'ends_on' => ['nullable', 'date', 'after_or_equal:'.$recurringExpense->starts_on->toDateString()],
        ]);

        $recurringExpense->update([...$validated, 'currency' => strtoupper($validated['currency'])]);
        $this->booker->bookDue($this->label());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring cost saved. Payments already booked are unchanged.')]);

        return back();
    }

    /**
     * Stop a recurring cost. Its booked payments stay in the accounts,
     * unless they are asked to go with it: for a cost that was entered
     * by mistake, or with a wrong amount or start.
     */
    public function destroyRecurring(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $deleted = $request->boolean('payments')
            ? Transaction::query()->where('recurring_expense_id', $recurringExpense->id)->delete()
            : null;

        $recurringExpense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => $deleted === null
            ? __('Recurring cost stopped. Payments already booked stay.')
            : trans_choice('Recurring cost deleted with its :count booked payment.|Recurring cost deleted with its :count booked payments.', $deleted)]);

        return back();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function amountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,15', 'gt:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'conversion_rate' => ['nullable', 'numeric', 'decimal:0,10', 'min:0.0000000001', 'max:9999999999'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * What the running recurring costs come to in a year, in the label's currency: each at its own
     * rate or the current reference rate. Costs whose rate cannot be had are counted apart.
     *
     * @return array{0: string, 1: int}
     */
    private function runningPerYear(Label $label): array
    {
        $sum = '0';
        $withoutRate = 0;

        foreach (RecurringExpense::query()->get()->filter(fn (RecurringExpense $expense): bool => $expense->nextDueOn() !== null) as $expense) {
            $rate = $this->rates->for($expense->currency, $label->currency, $expense->conversion_rate, today());

            if ($rate === null) {
                $withoutRate++;

                continue;
            }

            $perYear = Decimal::mul((string) $expense->amount, (string) (12 / $expense->interval->months()));
            $sum = Decimal::add($sum, Decimal::mul($perYear, $rate));
        }

        return [$sum, $withoutRate];
    }

    private function label(): Label
    {
        return $this->currentLabel->get() ?? abort(404);
    }
}
