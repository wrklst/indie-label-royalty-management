<?php

namespace App\Http\Controllers;

use App\Models\DataImport;
use App\Models\Item;
use App\Models\PlayCount;
use App\Models\Release;
use App\Models\Transaction;
use App\Services\Reports\IncomeReport;
use App\Services\Royalties\MoneyFlow;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $dateColumn = $request->input('date') === 'paid' ? 'paid_timestamp' : 'timestamp';
        $to = $request->date('to') ?? now()->endOfMonth();
        $from = $request->date('from') ?? $to->subMonths(11)->startOfMonth();

        $selection = fn () => Transaction::query()->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()]);

        $byType = $selection()->toBase()->selectRaw('type, SUM(converted_total) as total, COUNT(*) as transactions')->groupBy('type')->get()->keyBy('type');

        return Inertia::render('dashboard', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'date' => $dateColumn === 'paid_timestamp' ? 'paid' : 'sold'],
            'totals' => [
                'income' => Decimal::parse($byType['income']->total ?? 0) ?? '0',
                'expense' => Decimal::parse($byType['expense']->total ?? 0) ?? '0',
                'transactions' => (int) $byType->sum('transactions'),
            ],
            'flow' => MoneyFlow::of($selection()),
            'report' => IncomeReport::of(
                $selection(),
                PlayCount::query()->where('period_end', '>=', $from->toDateString())->where('period_start', '<=', $to->toDateString()),
                $dateColumn,
            ),
            'movers' => IncomeReport::movers($selection(), 'release_id', dateColumn: $dateColumn),
            'attention' => [
                'unassigned_items' => Item::query()->whereNull('release_id')->count(),
                'releases_without_contract' => Release::query()->whereNull('contract_id')->count(),
                'unconverted' => Transaction::query()->whereNull('converted_total')->count(),
                'imports' => DataImport::query()->where('needs_cleanup', true)->count(),
            ],
            'hasData' => Transaction::query()->exists(),
        ]);
    }
}
