<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractRequest;
use App\Models\Contract;
use App\Support\Decimal;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $contracts = Contract::query()
            ->with(['splits.payee:id,name', 'splits.ledger:id,name'])
            ->withCount(['releases', 'tracks', 'items', 'children', 'parents'])
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->boolean('unused'), fn ($query) => $query->doesntHave('releases')->doesntHave('tracks')->doesntHave('items')->doesntHave('parents'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Contract $contract): array => [
                ...$contract->only(['id', 'name', 'memo', 'releases_count', 'tracks_count', 'items_count', 'children_count', 'parents_count']),
                'splits' => $contract->splits->map(fn ($split): array => [
                    'payee' => $split->payee?->name,
                    'split_amount' => $split->split_amount,
                    'ledger' => $split->ledger?->name,
                ]),
            ]);

        return Inertia::render('contracts/index', [
            'contracts' => $contracts,
            'filters' => ['search' => $request->string('search')->value(), 'unused' => $request->boolean('unused')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contracts/form', [
            'contract' => null,
            ...$this->options(),
        ]);
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $contract = DB::transaction(function () use ($request): Contract {
            $contract = Contract::query()->create($request->safe()->only(['name', 'memo']));
            $this->sync($contract, $request);

            return $contract;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract created.')]);

        return to_route('contracts.edit', $contract);
    }

    public function edit(Contract $contract): Response
    {
        $contract->load(['splits', 'children:id,name', 'parents:id,name', 'releases:id,name,contract_id', 'tracks:id,name,contract_id,release_id', 'tracks.release:id,name']);

        return Inertia::render('contracts/form', [
            'contract' => [
                ...$contract->only(['id', 'name', 'memo']),
                'splits' => $contract->splits->map(fn ($split): array => [
                    'payee_id' => $split->payee_id,
                    'ledger_id' => $split->ledger_id,
                    'territory_id' => $split->territory_id,
                    'percentage' => rtrim(rtrim(Decimal::mul((string) $split->split_amount, '100'), '0'), '.'),
                ]),
                'children' => $contract->children->map(fn (Contract $child): array => [
                    'contract_id' => $child->id,
                    'weight' => rtrim(rtrim((string) $child->pivot->weight, '0'), '.'),
                ]),
                'parents' => $contract->parents->map->only(['id', 'name']),
                'releases' => $contract->releases->map->only(['id', 'name']),
                'tracks' => $contract->tracks->map(fn ($track): array => ['id' => $track->id, 'name' => $track->name, 'release' => $track->release?->name, 'release_id' => $track->release_id]),
            ],
            ...$this->options($contract),
        ]);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        DB::transaction(function () use ($request, $contract): void {
            $contract->update($request->safe()->only(['name', 'memo']));
            $this->sync($contract, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract saved.')]);

        return back();
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $contract->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contract deleted.')]);

        return to_route('contracts.index');
    }

    private function sync(Contract $contract, ContractRequest $request): void
    {
        $contract->splits()->delete();

        foreach ($request->validated('splits', []) as $split) {
            $contract->splits()->create([
                'payee_id' => $split['payee_id'],
                'ledger_id' => $split['ledger_id'] ?? null,
                'territory_id' => $split['territory_id'] ?? null,
                'split_amount' => bcdiv((string) $split['percentage'], '100', 9),
            ]);
        }

        $contract->children()->sync(collect($request->validated('children', []))
            ->mapWithKeys(fn (array $child): array => [$child['contract_id'] => ['weight' => $child['weight']]])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?Contract $contract = null): array
    {
        return [
            'payeeOptions' => Options::payees(),
            'ledgerOptions' => Options::ledgers(),
            'territoryOptions' => Options::territories(),
            'contractOptions' => collect(Options::contracts())->reject(fn (array $option): bool => $option['id'] === $contract?->id)->values(),
        ];
    }
}
