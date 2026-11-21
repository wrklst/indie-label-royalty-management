<?php

namespace App\Support;

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payout;
use App\Models\Period;
use App\Models\Statement;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use SortDirection;

/**
 * What one account may read as a payee, across labels. This is the only
 * place where the label scope is lifted for payees: every query here is
 * limited to the (label, payee) pairs of the user's own label_user rows
 * with the payee role, never to anything the request says.
 */
final class PayeeAccess
{
    /** @var Collection<int, Label>|null */
    private ?Collection $labels = null;

    public function __construct(private readonly User $user) {}

    /**
     * The labels the user is a payee of, with the linked payee in
     * `pivot->payee_id`. A payee row without a linked payee gives nothing.
     *
     * @return Collection<int, Label>
     */
    public function labels(): Collection
    {
        return $this->labels ??= $this->user->labels()
            ->wherePivot('role', LabelRole::Payee->value)
            ->wherePivotNotNull('payee_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether the user has a payee role anywhere, linked or not.
     */
    public function isPayeeAnywhere(): bool
    {
        return $this->user->labels()->wherePivot('role', LabelRole::Payee->value)->exists();
    }

    /**
     * The published, not archived statements of the user in every label
     * they are a payee of, or in one of them.
     *
     * @return Builder<Statement>
     */
    public function statements(?Label $label = null): Builder
    {
        $query = Statement::query()
            ->withoutGlobalScope('label')
            ->where('archived', false)
            ->whereNotNull('published_at')
            ->with(['period' => fn ($relation) => $relation->withoutGlobalScope('label')->select(['id', 'name', 'starts_on', 'ends_on'])]);

        return $this->limitToPairs($query, $label);
    }

    /**
     * The payouts recorded for the user in one of their labels.
     *
     * @return Builder<Payout>
     */
    public function payouts(Label $label): Builder
    {
        $query = Payout::query()
            ->withoutGlobalScope('label')
            ->with(['period' => fn ($relation) => $relation->withoutGlobalScope('label')->select(['id', 'name'])]);

        return $this->limitToPairs($query, $label);
    }

    /**
     * Order by a column of the period of the row; the period is looked up
     * by the row's own period_id, so no other row is reached.
     *
     * @param  literal-string  $foreignKey
     * @param  literal-string  $column
     */
    public function periodOrder(string $foreignKey, string $column): Closure
    {
        return function (Builder $query, SortDirection $direction) use ($foreignKey, $column): void {
            $query->orderBy(new Expression($foreignKey.' IS NULL'))->orderBy(
                Period::query()->withoutGlobalScope('label')->select($column)->whereColumn('periods.id', $query->qualifyColumn($foreignKey))->limit(1),
                $direction,
            );
        };
    }

    /**
     * @template TModel of Statement|Payout
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function limitToPairs(Builder $query, ?Label $label): Builder
    {
        $labels = $label === null ? $this->labels() : $this->labels()->where('id', $label->id);

        return $query->where(function (Builder $pairs) use ($labels): void {
            // No pair matches nothing.
            $pairs->whereRaw('1 = 0');

            foreach ($labels as $own) {
                $pairs->orWhere(fn (Builder $pair) => $pair
                    ->where($pair->qualifyColumn('label_id'), $own->id)
                    ->where($pair->qualifyColumn('payee_id'), $own->pivot->payee_id));
            }
        });
    }
}
