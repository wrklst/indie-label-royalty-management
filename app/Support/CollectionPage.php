<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * The page and order of a list that is worked out in PHP rather than read
 * from the database, such as the tracks of an artist with what each
 * earned. It follows the query string like `Sorting` does: `{prefix}_sort`,
 * `{prefix}_direction` and `{prefix}_page`.
 */
final class CollectionPage
{
    /**
     * @param  Collection<int, covariant array<string, mixed>>  $rows
     * @param  list<string>  $columns  The keys of a row the list can be sorted by.
     * @return array{page: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string|null>}
     */
    public static function of(Collection $rows, Request $request, array $columns, string $prefix, int $perPage = PageSize::SECONDARY): array
    {
        $sort = $request->query("{$prefix}_sort");
        $direction = $request->query("{$prefix}_direction", 'asc');
        $sorted = is_string($sort) && in_array($sort, $columns, true) && in_array($direction, ['asc', 'desc'], true);

        if ($sorted) {
            $sign = $direction === 'desc' ? -1 : 1;
            $rows = $rows->values()->sort(function (array $a, array $b) use ($sort, $sign): int {
                $left = $a[$sort] ?? null;
                $right = $b[$sort] ?? null;

                // Rows without a value come last whichever way the list is sorted.
                if ($left === null || $right === null || $left === '' || $right === '') {
                    return ((int) ($left === null || $left === '')) <=> ((int) ($right === null || $right === ''));
                }

                return $sign * (is_numeric($left) && is_numeric($right) ? (float) $left <=> (float) $right : strnatcasecmp((string) $left, (string) $right));
            })->values();
        }

        $pageName = "{$prefix}_page";
        $current = Paginator::resolveCurrentPage($pageName);
        $page = app()->makeWith(LengthAwarePaginator::class, [
            'items' => $rows->forPage($current, $perPage)->values(),
            'total' => $rows->count(),
            'perPage' => $perPage,
            'currentPage' => $current,
            'options' => ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName],
        ])->withQueryString();

        return [
            'page' => $page,
            'filters' => ["{$prefix}_sort" => $sorted ? $sort : null, "{$prefix}_direction" => $sorted ? $direction : null],
        ];
    }
}
