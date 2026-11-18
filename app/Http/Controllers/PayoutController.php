<?php

namespace App\Http\Controllers;

use App\Models\Payee;
use App\Models\Payout;
use App\Models\Period;
use App\Models\User;
use App\Support\Sorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $sorting = Sorting::for($request, [
            'paid_on' => 'paid_on',
            'payee' => Sorting::related(Payee::class, 'payee_id'),
            'period' => Sorting::related(Period::class, 'period_id'),
            'memo' => Sorting::nullsLast('memo'),
            'user' => Sorting::related(User::class, 'user_id'),
            'total' => 'total',
        ], fn (Builder $query) => $query->orderByDesc('paid_on')->orderByDesc('id'));

        return Inertia::render('payouts/index', [
            'payouts' => Payout::query()
                ->with(['payee:id,name', 'period:id,name', 'user:id,name', 'allocations.ledger:id,name'])
                ->tap($sorting)
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Payout $payout): array => [
                    ...$payout->only(['id', 'total', 'memo', 'payee_id', 'period_id']),
                    'paid_on' => $payout->paid_on->toDateString(),
                    'payee' => $payout->payee?->name,
                    'period' => $payout->period?->name,
                    'user' => $payout->user?->name,
                    'ledgers' => $payout->allocations->map(fn ($allocation): array => [
                        'name' => $allocation->ledger->name ?? 'Primary',
                        'amount' => $allocation->amount,
                    ]),
                ]),
            'filters' => $sorting->toArray(),
        ]);
    }

    public function destroy(Payout $payout): RedirectResponse
    {
        $payout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout deleted. The amount is payable again.')]);

        return back();
    }
}
