<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabelSettingsRequest;
use App\Http\Requests\TerritoryRequest;
use App\Models\Territory;
use App\Models\Transaction;
use App\Services\Releases\ZebralutionSheet;
use App\Services\Royalties\Tools\MechanicalsTool;
use App\Services\Royalties\Tools\ToolSettings;
use App\Support\CurrentLabel;
use App\Support\Distributors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LabelSettingsController extends Controller
{
    public function edit(CurrentLabel $currentLabel): Response
    {
        $label = $currentLabel->get();
        $tools = ToolSettings::for($label);

        return Inertia::render('settings/label', [
            'settings' => [
                ...$label->only(['name', 'currency', 'contact_email', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves']),
                'bandcamp_disable_fee' => (bool) $label->importerSetting('bandcamp.disable_fee', false),
                'bandcamp_exclude_physical' => (bool) $label->importerSetting('bandcamp.exclude_physical', false),
                'airplay_markets' => $label->airplayMarkets(),
                'zebralution_number' => $label->deliverySetting('zebralution.number'),
                'zebralution_origin_country' => $label->deliverySetting('zebralution.origin_country'),
                'zebralution_price_group' => (int) $label->deliverySetting('zebralution.price_group', 20),
                'zebralution_work_rights' => $label->deliverySetting('zebralution.work_rights', ZebralutionSheet::WORK_RIGHTS[0]),
            ],
            'distributors' => collect(Distributors::NAMES)->map(fn (string $name, string $key): array => ['key' => $key, 'name' => $name, 'enabled' => $label->usesDistributor($key)])->values(),
            'zebralutionWorkRights' => ZebralutionSheet::WORK_RIGHTS,
            'royaltyTools' => [
                'mechanicals_usd_rate' => $tools->usdRate === null ? null : MechanicalsTool::plain($tools->usdRate),
                'mechanicals_include_physical' => $tools->includePhysical,
                'ignore_negative_quantities' => $tools->ignoreNegativeQuantities,
            ],
            'territories' => Territory::query()->orderBy('name')->get()->map(fn (Territory $territory): array => [...$territory->only(['id', 'name', 'countries']), 'delete_block' => $territory->deleteBlock()]),
        ]);
    }

    public function update(LabelSettingsRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $currentLabel->get();
        $tools = ToolSettings::for($label);

        if ($request->validated('currency') !== $label->currency && Transaction::query()->exists()) {
            throw ValidationException::withMessages([
                'currency' => "The label already has transactions in {$label->currency}. Changing the accounting currency would not convert them.",
            ]);
        }

        $label->update([
            ...$request->safe()->only(['name', 'currency', 'contact_email', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves']),
            'importer_settings' => array_replace_recursive($label->importer_settings ?? [], ['bandcamp' => [
                'disable_fee' => $request->boolean('bandcamp_disable_fee'),
                'exclude_physical' => $request->boolean('bandcamp_exclude_physical'),
            ]]),
            'airplay_markets' => $request->validated('airplay_markets', $label->airplay_markets),
            // A form that does not send the distributors or the Zebralution settings leaves them alone.
            'delivery_settings' => array_replace(
                $label->delivery_settings ?? [],
                $request->has('distributors') ? ['distributors' => array_map(boolval(...), $request->validated('distributors'))] : [],
                $request->has('zebralution_number') ? ['zebralution' => [
                    'number' => $request->validated('zebralution_number'),
                    'origin_country' => $request->validated('zebralution_origin_country'),
                    'price_group' => (int) ($request->validated('zebralution_price_group') ?? 20),
                    'work_rights' => $request->validated('zebralution_work_rights') ?? ZebralutionSheet::WORK_RIGHTS[0],
                ]] : [],
            ) ?: null,
            // A form that does not send the tool settings leaves them alone.
            'royalty_tool_settings' => [
                'usd_rate' => $request->has('mechanicals_usd_rate') ? $request->validated('mechanicals_usd_rate') : $tools->usdRate,
                'include_physical' => $request->boolean('mechanicals_include_physical', $tools->includePhysical),
                'ignore_negative_quantities' => $request->boolean('ignore_negative_quantities', $tools->ignoreNegativeQuantities),
            ],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label settings saved.')]);

        return back();
    }

    public function storeTerritory(TerritoryRequest $request): RedirectResponse
    {
        Territory::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Territory created.')]);

        return back();
    }

    /**
     * A territory that splits still refer to stays: without it they
     * would apply worldwide and pay out on every sale.
     */
    public function destroyTerritory(Territory $territory): RedirectResponse
    {
        if ($reason = $territory->deleteBlock()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $reason]);

            return back();
        }

        $territory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Territory deleted.')]);

        return back();
    }
}
