<?php

namespace App\Http\Controllers;

use App\Enums\RecurrenceInterval;
use App\Enums\TransactionType;
use App\Models\Label;
use App\Models\Receipt;
use App\Models\RecurringExpense;
use App\Models\Transaction;
use App\Services\Receipts\AiConnection;
use App\Services\Royalties\RecurringExpenseBooker;
use App\Support\CurrentLabel;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Running costs of the label itself: subscriptions, admin, anything not
 * tied to a release. They are expenses the label bears alone, so they
 * lower its result in the profitability report and never touch royalties.
 */
class LabelCostController extends Controller
{
    public function __construct(private readonly CurrentLabel $currentLabel, private readonly RecurringExpenseBooker $booker) {}

    public function index(): Response
    {
        $label = $this->label();
        $this->booker->bookDue($label);

        $costs = fn () => Transaction::query()->where('type', TransactionType::Expense)->where('recoupable', false)->whereNull('release_id');
        $year = now()->year;
        $listed = $costs()->orderByDesc('paid_timestamp')->orderByDesc('id')->limit(200)->get();
        $receipts = Receipt::query()->orderByDesc('id')->get();
        $withReceipt = $receipts->whereNotNull('transaction_id')->keyBy('transaction_id');
        $withoutReceipt = $listed->reject(fn (Transaction $cost): bool => $withReceipt->has($cost->id));

        return Inertia::render('label-costs/index', [
            'currency' => $label->currency,
            'categories' => config('royalties.categories.overhead'),
            'intervals' => array_map(fn (RecurrenceInterval $interval): array => ['value' => $interval->value, 'label' => $interval->label()], RecurrenceInterval::cases()),
            'totals' => [
                'this_year' => Decimal::parse($costs()->whereYear('paid_timestamp', $year)->sum('converted_total')) ?? '0',
                'last_year' => Decimal::parse($costs()->whereYear('paid_timestamp', $year - 1)->sum('converted_total')) ?? '0',
                'year' => $year,
            ],
            'recurring' => RecurringExpense::query()->orderBy('name')->get()->map(fn (RecurringExpense $expense): array => [
                ...$expense->only(['id', 'name', 'category', 'amount', 'currency', 'memo', 'occurrences_booked']),
                'conversion_rate' => $expense->conversion_rate === null ? null : rtrim(rtrim((string) $expense->conversion_rate, '0'), '.'),
                'interval' => $expense->interval->value,
                'starts_on' => $expense->starts_on->toDateString(),
                'ends_on' => $expense->ends_on?->toDateString(),
                'next_due_on' => $expense->nextDueOn()?->toDateString(),
            ]),
            'costs' => $listed->map(fn (Transaction $cost): array => [
                ...$cost->only(['id', 'category', 'memo', 'source', 'total', 'converted_total', 'currency']),
                'paid_on' => $cost->paid_timestamp->toDateString(),
                'recurring' => $cost->recurring_expense_id !== null,
                'receipt_id' => $withReceipt->get($cost->id)?->id,
            ])->values(),
            'receiptReading' => AiConnection::isConfigured($label),
            // Receipts that belong to no cost yet, with what was read off them.
            'receipts' => $receipts->whereNull('transaction_id')->map(fn (Receipt $receipt): array => [
                ...$receipt->only(['id', 'original_name', 'status', 'vendor', 'currency', 'category', 'summary', 'error']),
                'amount' => $receipt->amount === null ? null : Decimal::round($receipt->amount, 2),
                'paid_on' => $receipt->paid_on?->toDateString(),
                'uploaded_on' => $receipt->created_at?->toDateString(),
                'suggested_cost_id' => ReceiptController::match($receipt, $withoutReceipt),
            ])->values(),
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
            'paid_on' => ['required', 'date'],
            'receipt_id' => ['nullable', 'integer', Rule::exists('receipts', 'id')->where('label_id', $label->id)->whereNull('transaction_id')],
        ]);

        $currency = strtoupper($validated['currency']);
        $total = Decimal::sub('0', Decimal::parse($validated['amount']));
        $rate = $currency === $label->currency ? '1' : Decimal::parse($validated['conversion_rate'] ?? null);

        $cost = Transaction::query()->create([
            'type' => TransactionType::Expense,
            'recoupable' => false,
            'timestamp' => $validated['paid_on'],
            'paid_timestamp' => $validated['paid_on'],
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
            ? trans_choice('Recurring cost added and :count past payment booked.|Recurring cost added and :count past payments booked.', $booked)
            : __('Recurring cost added. It is booked on each due date.')]);

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

    public function destroyRecurring(RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring cost stopped. Payments already booked stay.')]);

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

    private function label(): Label
    {
        return $this->currentLabel->get() ?? abort(404);
    }
}
