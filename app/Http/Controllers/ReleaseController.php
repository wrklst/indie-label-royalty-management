<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseRequest;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Imports\Identifiers;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseController extends Controller
{
    public function index(Request $request): Response
    {
        $releases = Release::query()
            ->with(['artist:id,name', 'contract:id,name'])
            ->withCount('tracks')
            ->withSum(['transactions as income' => fn ($query) => $query->where('type', 'income')], 'converted_total')
            ->when($request->string('search')->trim()->value(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('catalog_number', 'like', "%{$search}%")
                ->orWhere('upc', 'like', "%{$search}%")))
            ->when($request->integer('artist'), fn ($query, int $artist) => $query->where('artist_id', $artist))
            ->when($request->boolean('without_contract'), fn ($query) => $query->whereNull('contract_id'))
            ->orderByRaw('catalog_number IS NULL')->orderBy('catalog_number')->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Release $release): array => [
                ...$release->only(['id', 'name', 'catalog_number', 'upc', 'tracks_count']),
                'released_on' => $release->released_on?->toDateString(),
                'artist' => $release->artist?->name,
                'contract' => $release->contract?->only(['id', 'name']),
                'income' => (string) ($release->income ?? '0'),
            ]);

        return Inertia::render('releases/index', [
            'releases' => $releases,
            'filters' => [
                'search' => $request->string('search')->value(),
                'artist' => $request->integer('artist') ?: null,
                'without_contract' => $request->boolean('without_contract'),
            ],
            'artistOptions' => Options::artists(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('releases/form', ['release' => null, ...$this->options()]);
    }

    public function store(ReleaseRequest $request): RedirectResponse
    {
        $release = DB::transaction(function () use ($request): Release {
            $release = Release::query()->create($this->attributes($request));
            $this->syncTracks($release, $request);

            return $release;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release created.')]);

        return to_route('releases.show', $release);
    }

    public function show(Release $release): Response
    {
        $release->load(['artist:id,name', 'contract:id,name', 'tracks.artist:id,name', 'tracks.contract:id,name', 'items.contract:id,name', 'items.track:id,name']);

        $incomeByTrack = Transaction::query()->where('release_id', $release->id)->where('type', 'income')
            ->selectRaw('track_id, SUM(converted_total) as total, SUM(quantity) as quantity')->groupBy('track_id')->get()->keyBy('track_id');
        $byType = Transaction::query()->where('release_id', $release->id)
            ->selectRaw('type, SUM(converted_total) as total, COUNT(*) as transactions')->groupBy('type')->get()->keyBy(fn ($row) => $row->type->value);
        $byRetailer = Transaction::query()->where('release_id', $release->id)->where('type', 'income')
            ->selectRaw('retailer, SUM(converted_total) as total, SUM(quantity) as quantity')->groupBy('retailer')->orderByDesc('total')->limit(15)->get();

        return Inertia::render('releases/show', [
            'release' => [
                ...$release->only(['id', 'name', 'catalog_number', 'upc', 'spotify_id', 'discogs_id', 'notes']),
                'released_on' => $release->released_on?->toDateString(),
                'artist' => $release->artist?->name,
                'contract' => $release->contract?->only(['id', 'name']),
                'tracks' => $release->tracks->sortBy([['track_number', 'asc'], ['id', 'asc']])->values()->map(fn (Track $track): array => [
                    ...$track->only(['id', 'name', 'isrc', 'track_number', 'duration']),
                    'artist' => $track->artist?->name,
                    'contract' => $track->contract?->only(['id', 'name']),
                    'income' => (string) ($incomeByTrack[$track->id]->total ?? '0'),
                    'quantity' => (string) ($incomeByTrack[$track->id]->quantity ?? '0'),
                ]),
                'items' => $release->items->map(fn ($item): array => [
                    ...$item->only(['id', 'name', 'format_group', 'is_additionals']),
                    'track' => $item->track?->name,
                    'contract' => $item->contract?->only(['id', 'name']),
                ]),
            ],
            'totals' => [
                'income' => (string) ($byType['income']->total ?? '0'),
                'expense' => (string) ($byType['expense']->total ?? '0'),
                'transactions' => (int) $byType->sum('transactions'),
                'release_level_income' => (string) ($incomeByTrack->get('')->total ?? '0'),
            ],
            'retailers' => $byRetailer->map(fn ($row): array => [
                'retailer' => $row->retailer ?? 'Unknown', 'total' => (string) $row->total, 'quantity' => (string) $row->quantity,
            ]),
        ]);
    }

    public function edit(Release $release): Response
    {
        $release->load('tracks');

        return Inertia::render('releases/form', [
            'release' => [
                ...$release->only(['id', 'name', 'artist_id', 'contract_id', 'catalog_number', 'upc', 'spotify_id', 'discogs_id', 'accounts_mechanicals', 'mechanicals_rate', 'ppd_rate', 'reserves_rate', 'notes']),
                'released_on' => $release->released_on?->toDateString(),
                'tracks' => $release->tracks->sortBy([['track_number', 'asc'], ['id', 'asc']])->values()
                    ->map->only(['id', 'name', 'artist_id', 'contract_id', 'isrc', 'iswc', 'track_number', 'duration']),
            ],
            ...$this->options(),
        ]);
    }

    public function update(ReleaseRequest $request, Release $release): RedirectResponse
    {
        DB::transaction(function () use ($request, $release): void {
            $release->update($this->attributes($request));
            $this->syncTracks($release, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release saved.')]);

        return to_route('releases.show', $release);
    }

    public function destroy(Release $release): RedirectResponse
    {
        $release->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release deleted. Its transactions are kept and now unassigned.')]);

        return to_route('releases.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ReleaseRequest $request): array
    {
        $attributes = $request->safe()->except('tracks');

        if (array_key_exists('upc', $attributes)) {
            $attributes['upc'] = Identifiers::upc($attributes['upc']);
        }

        return $attributes;
    }

    /**
     * Update, create and remove tracks to match the submitted rows.
     */
    private function syncTracks(Release $release, ReleaseRequest $request): void
    {
        if (! $request->has('tracks')) {
            return;
        }

        $kept = [];

        foreach ($request->validated('tracks', []) as $row) {
            $attributes = [
                'name' => $row['name'],
                'artist_id' => $row['artist_id'] ?? null,
                'contract_id' => $row['contract_id'] ?? null,
                'isrc' => Identifiers::isrc($row['isrc'] ?? null),
                'iswc' => $row['iswc'] ?? null,
                'track_number' => $row['track_number'] ?? null,
                'duration' => $row['duration'] ?? null,
            ];

            $track = isset($row['id']) ? $release->tracks()->whereKey($row['id'])->first() : null;
            $track ? $track->update($attributes) : $track = $release->tracks()->create([...$attributes, 'label_id' => $release->label_id]);
            $kept[] = $track->id;
        }

        $release->tracks()->whereNotIn('id', $kept)->get()->each->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return ['artistOptions' => Options::artists(), 'contractOptions' => Options::contracts()];
    }
}
