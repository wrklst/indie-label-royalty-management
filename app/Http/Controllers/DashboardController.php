<?php

namespace App\Http\Controllers;

use App\Models\DataImport;
use App\Models\Item;
use App\Models\Release;
use App\Models\Transaction;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $dateColumn = $request->input('date') === 'paid' ? 'paid_timestamp' : 'timestamp';
        $to = $request->date('to') ?? now()->endOfMonth();
        $from = $request->date('from') ?? $to->subMonths(11)->startOfMonth();

        $range = fn () => Transaction::query()->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])->toBase();
        $income = fn () => $range()->where('type', 'income');

        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$dateColumn})"
            : "DATE_FORMAT({$dateColumn}, '%Y-%m')";

        $byType = $range()->selectRaw('type, SUM(converted_total) as total, COUNT(*) as transactions')->groupBy('type')->get()->keyBy('type');

        $breakdown = fn (string $column, int $limit = 12) => $income()
            ->selectRaw("{$column} as label, SUM(converted_total) as total, SUM(quantity) as quantity")
            ->groupBy($column)->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($row): array => ['label' => $row->label, 'total' => Decimal::parse($row->total) ?? '0', 'quantity' => Decimal::parse($row->quantity) ?? '0']);

        return Inertia::render('dashboard', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'date' => $dateColumn === 'paid_timestamp' ? 'paid' : 'sold'],
            'totals' => [
                'income' => Decimal::parse($byType['income']->total ?? 0) ?? '0',
                'expense' => Decimal::parse($byType['expense']->total ?? 0) ?? '0',
                'transactions' => (int) $byType->sum('transactions'),
            ],
            'months' => $income()->selectRaw("{$month} as label, SUM(converted_total) as total")->groupByRaw('1')->orderByRaw('1')->get()
                ->map(fn ($row): array => ['label' => $row->label, 'total' => Decimal::parse($row->total) ?? '0']),
            'retailers' => $breakdown('retailer'),
            'formats' => $breakdown('format'),
            'regions' => $breakdown('region', 15),
            'releases' => $income()->join('releases', 'releases.id', '=', 'transactions.release_id')
                ->selectRaw('releases.id, releases.name as label, SUM(transactions.converted_total) as total, SUM(transactions.quantity) as quantity')
                ->groupBy('releases.id', 'releases.name')->orderByDesc('total')->limit(12)->get()
                ->map(fn ($row): array => ['id' => $row->id, 'label' => $row->label, 'total' => Decimal::parse($row->total) ?? '0', 'quantity' => Decimal::parse($row->quantity) ?? '0']),
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
