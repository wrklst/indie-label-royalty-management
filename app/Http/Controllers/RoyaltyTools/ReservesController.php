<?php

namespace App\Http\Controllers\RoyaltyTools;

use App\Enums\RoyaltyTool;
use App\Services\Royalties\Tools\ReservesTool;
use App\Services\Royalties\Tools\ToolRuns;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservesController extends ToolController
{
    public function __construct(CurrentLabel $currentLabel, ToolRuns $runs, private readonly ReservesTool $reserves)
    {
        parent::__construct($currentLabel, $runs);
    }

    protected function tool(): RoyaltyTool
    {
        return RoyaltyTool::Reserves;
    }

    public function index(Request $request): Response
    {
        $label = $this->label();
        $input = $request->hasAny(['from', 'to']) ? $request->validate($this->rangeRules()) : null;

        return Inertia::render('royalty-tools/reserves', [
            ...$this->common($request, $label, $input['from'] ?? null, $input['to'] ?? null),
            'filters' => ['from' => $input['from'] ?? null, 'to' => $input['to'] ?? null],
            'report' => $input === null ? null : $this->reserves->report($label, $input['from'], $input['to']),
            'balances' => $this->reserves->balances($label),
        ]);
    }

    /**
     * Both lines of a reserve are booked at once: held on one date and
     * given back on the other.
     */
    public function store(Request $request): RedirectResponse
    {
        $label = $this->label();
        $dates = ['required', 'date_format:Y-m-d', 'after:1999-12-31', 'before:2100-01-01'];
        $input = $request->validate([
            ...$this->rangeRules(),
            'held_on' => $dates,
            'liquidated_on' => [...$dates, 'after_or_equal:held_on'],
            'confirm_overlap' => ['boolean'],
        ]);

        $report = $this->reserves->report($label, $input['from'], $input['to']);

        return $this->book($request, $label, $input['from'], $input['to'], $input['held_on'], $report['total'], [
            'held_on' => $input['held_on'],
            'liquidated_on' => $input['liquidated_on'],
        ], $this->reserves->lines($report, $input['held_on'], $input['liquidated_on']));
    }
}
