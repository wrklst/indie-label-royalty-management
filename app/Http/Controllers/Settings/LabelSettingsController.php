<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabelSettingsRequest;
use App\Http\Requests\TerritoryRequest;
use App\Models\Territory;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LabelSettingsController extends Controller
{
    public function edit(CurrentLabel $currentLabel): Response
    {
        $label = $currentLabel->get();

        return Inertia::render('settings/label', [
            'settings' => [
                ...$label->only(['name', 'currency', 'contact_email', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves']),
                'bandcamp_disable_fee' => (bool) $label->importerSetting('bandcamp.disable_fee', false),
                'bandcamp_exclude_physical' => (bool) $label->importerSetting('bandcamp.exclude_physical', false),
            ],
            'territories' => Territory::query()->orderBy('name')->get()->map->only(['id', 'name', 'countries']),
        ]);
    }

    public function update(LabelSettingsRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $currentLabel->get()->update([
            ...$request->safe()->only(['name', 'currency', 'contact_email', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves']),
            'importer_settings' => ['bandcamp' => [
                'disable_fee' => $request->boolean('bandcamp_disable_fee'),
                'exclude_physical' => $request->boolean('bandcamp_exclude_physical'),
            ]],
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

    public function destroyTerritory(Territory $territory): RedirectResponse
    {
        $territory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Territory deleted.')]);

        return back();
    }
}
