<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use SortDirection;

/**
 * Orders a paginated list by the column the user clicked. Only the keys a
 * controller lists can be sorted by; request input never reaches the SQL.
 */
final readonly class Sorting
{
    /**
     * @param  array<string, string|Closure>  $columns  Column key => a column name, or a closure receiving the query and the SortDirection.
     * @param  Closure  $default  Receives the query and applies the order used when no column is chosen.
     */
    private function __construct(
        private array $columns,
        private Closure $default,
        private ?string $sort,
        private ?SortDirection $direction,
    ) {}

    /**
     * Read `sort` and `direction` from the query string. An unknown key or
     * direction leaves the list in its default order.
     *
     * @param  array<string, string|Closure>  $columns
     */
    public static function for(Request $request, array $columns, Closure $default): self
    {
        $sort = $request->query('sort');
        $direction = match ($request->query('direction', 'asc')) {
            'asc' => SortDirection::Ascending,
            'desc' => SortDirection::Descending,
            default => null,
        };

        if (! is_string($sort) || ! array_key_exists($sort, $columns) || $direction === null) {
            return new self($columns, $default, null, null);
        }

        return new self($columns, $default, $sort, $direction);
    }

    /**
     * Order by a nullable column with the empty values at the end, whichever
     * way the list is sorted.
     *
     * @param  literal-string  $column
     */
    public static function nullsLast(string $column): Closure
    {
        return function (Builder $query, SortDirection $direction) use ($column): void {
            $query->orderBy(new Expression($column.' IS NULL'))
                ->orderBy($column, $direction);
        };
    }

    /**
     * Order by a `withSum()` alias, counting rows without anything to sum
     * as zero.
     *
     * @param  literal-string  $alias
     */
    public static function sum(string $alias): Closure
    {
        return function (Builder $query, SortDirection $direction) use ($alias): void {
            $query->orderBy(new Expression('COALESCE('.$alias.', 0)'), $direction);
        };
    }

    /**
     * Order by a column of a parent record, such as the name of a release's
     * artist. Rows without that parent come last.
     *
     * @param  class-string<Model>  $related
     * @param  literal-string  $foreignKey
     */
    public static function related(string $related, string $foreignKey, string $column = 'name'): Closure
    {
        return function (Builder $query, SortDirection $direction) use ($related, $foreignKey, $column): void {
            $parent = new $related;

            $query->orderBy(new Expression($foreignKey.' IS NULL'))
                ->orderBy(
                    $parent->newQuery()->select($column)->whereColumn($parent->getQualifiedKeyName(), $query->qualifyColumn($foreignKey))->limit(1),
                    $direction,
                );
        };
    }

    /**
     * Apply the order to a list query: `$query->tap($sorting)`.
     *
     * @param  Builder<covariant Model>  $query
     */
    public function __invoke(Builder $query): void
    {
        $column = $this->sort === null ? null : $this->columns[$this->sort];

        if ($column === null || $this->direction === null) {
            ($this->default)($query);
        } elseif ($column instanceof Closure) {
            $column($query, $this->direction);
        } else {
            $query->orderBy($column, $this->direction);
        }

        $key = $query->getModel()->getQualifiedKeyName();
        $ordered = array_column($query->getQuery()->orders ?? [], 'column');

        if (! in_array($key, $ordered, true) && ! in_array($query->getModel()->getKeyName(), $ordered, true)) {
            $query->orderBy($key);
        }
    }

    /**
     * The active order for the page's `filters` prop; both null in the
     * default order.
     *
     * @return array{sort: string|null, direction: 'asc'|'desc'|null}
     */
    public function toArray(): array
    {
        return [
            'sort' => $this->sort,
            'direction' => match ($this->direction) {
                SortDirection::Ascending => 'asc',
                SortDirection::Descending => 'desc',
                null => null,
            },
        ];
    }
}
