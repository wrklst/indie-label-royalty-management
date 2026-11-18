<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Models\Track;
use App\Models\Transaction;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Item::query()
            ->with(['release:id,name,catalog_number', 'track:id,name', 'contract:id,name'])
            ->withCount('transactions')
            ->withSum('transactions as total', 'converted_total')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('isrc', 'like', "%{$search}%")
                ->orWhere('upc', 'like', "%{$search}%")
                ->orWhere('catalog_number', 'like', "%{$search}%")))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('release_id'))
            ->when($request->integer('release'), fn ($query, int $release) => $query->where('release_id', $release))
            ->orderByRaw('release_id IS NOT NULL')->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Item $item): array => [
                ...$item->only(['id', 'name', 'artist_name', 'track_name', 'catalog_number', 'isrc', 'upc', 'format_group', 'is_additionals', 'release_id', 'track_id', 'contract_id', 'transactions_count']),
                'release' => $item->release?->only(['id', 'name', 'catalog_number']),
                'track' => $item->track?->only(['id', 'name']),
                'contract' => $item->contract?->only(['id', 'name']),
                'total' => (string) ($item->total ?? '0'),
            ]);

        return Inertia::render('items/index', [
            'items' => $items,
            'filters' => [
                'search' => $request->string('search')->value(),
                'unassigned' => $request->boolean('unassigned'),
                'release' => $request->integer('release') ?: null,
            ],
            'unassignedCount' => Item::query()->whereNull('release_id')->count(),
            'releaseOptions' => Options::releases(),
            'trackOptions' => Options::tracks(),
            'contractOptions' => Options::contracts(),
        ]);
    }

    /**
     * Assign an item to the catalog and move its transactions along.
     */
    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $attributes = $request->validated();

        if (! empty($attributes['track_id'])) {
            $attributes['release_id'] = Track::query()->findOrFail($attributes['track_id'])->release_id;
        }

        DB::transaction(function () use ($item, $attributes): void {
            $item->update($attributes);

            Transaction::query()->where('item_id', $item->id)->update([
                'release_id' => $item->release_id,
                'track_id' => $item->track_id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item saved and :count transactions updated.', ['count' => $item->transactions()->count()])]);

        return back();
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->transactions()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This item has transactions. Delete or reassign them first.')]);

            return back();
        }

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return back();
    }
}
