<?php

namespace App\Http\Controllers\RoyaltyTools;

use App\Enums\RoyaltyTool;
use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Services\Royalties\Tools\ToolRuns;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * What the mechanicals, PPD and reserves tools share: each exists only
 * for a label that switched it on, reports on a range of paid dates and
 * refuses to book the same range twice without being told to.
 *
 * @phpstan-import-type Line from ToolRuns
 */
abstract class ToolController extends Controller
{
    public function __construct(protected readonly CurrentLabel $currentLabel, protected readonly ToolRuns $runs) {}

    abstract protected function tool(): RoyaltyTool;

    protected function label(): Label
    {
        $label = $this->currentLabel->get() ?? abort(404);

        abort_unless($this->tool()->enabledFor($label), 404);

        return $label;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rangeRules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'after:1999-12-31'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before:2100-01-01'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function bookingRules(): array
    {
        return [
            'paid_on' => ['required', 'date_format:Y-m-d', 'after:1999-12-31', 'before:2100-01-01'],
            'confirm_overlap' => ['boolean'],
        ];
    }

    /**
     * The props every tool page shows next to its report.
     *
     * @return array{currency: string, runs: list<array<string, mixed>>, overlaps: list<array<string, mixed>>}
     */
    protected function common(Label $label, ?string $from, ?string $to): array
    {
        return [
            'currency' => $label->currency,
            'runs' => $this->runs->history($label, $this->tool()),
            'overlaps' => $from === null || $to === null ? [] : $this->runs->overlapping($label, $this->tool(), $from, $to),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  numeric-string  $total
     * @param  list<Line>  $lines
     */
    protected function book(Request $request, Label $label, string $from, string $to, string $paidOn, string $total, array $options, array $lines): RedirectResponse
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['from' => 'There is nothing to book for this range.']);
        }

        // Not documented for Infinite Catalog: a second run over the same
        // paid dates needs an explicit yes, because it counts sales twice.
        if (! $request->boolean('confirm_overlap') && $this->runs->overlapping($label, $this->tool(), $from, $to) !== []) {
            throw ValidationException::withMessages([
                'confirm_overlap' => 'An earlier run covers part of this range. Confirm that you want to book it again.',
            ]);
        }

        $import = $this->runs->record($label, $this->tool(), $request->user(), $from, $to, $paidOn, $total, $options, $lines);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            ':count transaction created.|:count transactions created.', $import->transactions_count,
        )]);

        return to_route("royalty-tools.{$this->tool()->value}.index");
    }
}
