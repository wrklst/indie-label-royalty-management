<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('payouts/index', [
            'payouts' => Payout::query()
                ->with(['payee:id,name', 'period:id,name', 'user:id,name', 'allocations.ledger:id,name'])
                ->orderByDesc('paid_on')->orderByDesc('id')
                ->paginate(50)
                ->through(fn (Payout $payout): array => [
                    ...$payout->only(['id', 'total', 'memo', 'payee_id', 'period_id']),
                    'paid_on' => $payout->paid_on->toDateString(),
                    'payee' => $payout->payee?->name,
                    'period' => $payout->period?->name,
                    'user' => $payout->user?->name,
                    'ledgers' => $payout->allocations->map(fn ($allocation): array => [
                        'name' => $allocation->ledger?->name ?? 'Primary',
                        'amount' => $allocation->amount,
                    ]),
                ]),
        ]);
    }

    public function destroy(Payout $payout): RedirectResponse
    {
        $payout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout deleted. The amount is payable again.')]);

        return back();
    }
}
