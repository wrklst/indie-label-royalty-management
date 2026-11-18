<?php

namespace App\Services\Royalties;

use App\Models\Contract;
use App\Support\Decimal;

/**
 * Flattens contracts into the payee splits that actually apply.
 *
 * A plain contract pays its own splits. A prorated contract (one with
 * child contracts, used for compilations and bundles) pays the splits of
 * its children instead, each scaled by the child's share of the weights.
 */
class SplitResolver
{
    private const int MAX_DEPTH = 5;

    /**
     * @return array<int, list<array{payee_id: int, ledger_id: int|null, countries: list<string>|null, fraction: string, contract_id: int}>>
     */
    public function forLabel(int $labelId): array
    {
        $contracts = Contract::query()
            ->withoutGlobalScope('label')
            ->where('label_id', $labelId)
            ->with(['splits.territory', 'children:id'])
            ->get()
            ->keyBy('id');

        $own = [];
        $children = [];

        foreach ($contracts as $contract) {
            $own[$contract->id] = $contract->splits->map(fn ($split): array => [
                'payee_id' => $split->payee_id,
                'ledger_id' => $split->ledger_id,
                'countries' => $split->territory?->countries,
                'fraction' => (string) $split->split_amount,
                'contract_id' => $contract->id,
            ])->all();

            $children[$contract->id] = $contract->children
                ->filter(fn (Contract $child): bool => Decimal::isPositive((string) $child->pivot->weight))
                ->map(fn (Contract $child): array => ['id' => $child->id, 'weight' => (string) $child->pivot->weight])
                ->values()->all();
        }

        $resolved = [];

        foreach (array_keys($own) as $contractId) {
            $resolved[$contractId] = $this->expand($contractId, $own, $children, '1', 0, []);
        }

        return $resolved;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $own
     * @param  array<int, list<array{id: int, weight: string}>>  $children
     * @param  list<int>  $path
     * @return list<array<string, mixed>>
     */
    private function expand(int $contractId, array $own, array $children, string $scale, int $depth, array $path): array
    {
        if (in_array($contractId, $path, true) || $depth > self::MAX_DEPTH) {
            return [];
        }

        $links = $children[$contractId] ?? [];

        if ($links === []) {
            return array_map(fn (array $split): array => [
                ...$split,
                'fraction' => Decimal::mul($split['fraction'], $scale),
            ], $own[$contractId] ?? []);
        }

        $totalWeight = array_reduce($links, fn (string $sum, array $link): string => Decimal::add($sum, $link['weight']), '0');
        $splits = [];

        foreach ($links as $link) {
            $share = Decimal::mul($scale, Decimal::div($link['weight'], $totalWeight));

            array_push($splits, ...$this->expand($link['id'], $own, $children, $share, $depth + 1, [...$path, $contractId]));
        }

        return $splits;
    }
}
