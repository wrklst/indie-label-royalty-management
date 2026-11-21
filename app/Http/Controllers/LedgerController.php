<?php

namespace App\Http\Controllers;

use App\Http\Requests\LedgerRequest;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ledgers/index', [
            'ledgers' => Ledger::query()
                ->withCount('splits')
                ->with(['splits.payee:id,name', 'splits.contract:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Ledger $ledger): array => [
                    'id' => $ledger->id,
                    'name' => $ledger->name,
                    'splits_count' => $ledger->splits_count,
                    'payees' => $ledger->splits->pluck('payee.name')->filter()->unique()->values()->all(),
                    'contracts' => $ledger->splits->pluck('contract.name')->filter()->unique()->values()->all(),
                ]),
        ]);
    }

    public function store(LedgerRequest $request): RedirectResponse
    {
        Ledger::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger created.')]);

        return back();
    }

    public function update(LedgerRequest $request, Ledger $ledger): RedirectResponse
    {
        $ledger->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger saved.')]);

        return back();
    }

    public function destroy(Ledger $ledger): RedirectResponse
    {
        $ledger->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger deleted. Its splits now book to the primary ledger.')]);

        return back();
    }
}
