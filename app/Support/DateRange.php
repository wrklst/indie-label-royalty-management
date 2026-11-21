<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The dates a report is limited to: `from` and `to` in the query string,
 * and `date=paid` to count by paid date instead of sale date. One reading
 * for every page that has the control, with `to >= from` checked here.
 */
final readonly class DateRange
{
    public function __construct(public ?string $from, public ?string $to, public bool $paid) {}

    /**
     * The validation rules of a range. A royalty tool needs both dates, in
     * a sensible span; a filter takes either or none.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $required = false): array
    {
        if ($required) {
            return [
                'from' => ['required', 'date_format:Y-m-d', 'after:1999-12-31'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before:2100-01-01'],
            ];
        }

        return ['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']];
    }

    /**
     * Read the range of the request, validating it.
     */
    public static function fromRequest(Request $request): self
    {
        $request->validate([...self::rules(), 'date' => ['nullable', Rule::in(['sold', 'paid'])]]);

        return new self($request->date('from')?->toDateString(), $request->date('to')?->toDateString(), $request->input('date') === 'paid');
    }

    /**
     * The transaction column the range counts by.
     *
     * @return 'paid_timestamp'|'timestamp'
     */
    public function column(): string
    {
        return $this->paid ? 'paid_timestamp' : 'timestamp';
    }

    /**
     * Limit a transaction query to the range.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->from, fn ($query, string $from) => $query->where($this->column(), '>=', $from))
            ->when($this->to, fn ($query, string $to) => $query->where($this->column(), '<=', $to));
    }

    /**
     * The range for the page's `filters` prop.
     *
     * @return array{from: string|null, to: string|null, date: 'paid'|null}
     */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'date' => $this->paid ? 'paid' : null];
    }
}
