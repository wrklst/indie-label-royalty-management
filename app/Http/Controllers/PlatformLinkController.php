<?php

namespace App\Http\Controllers;

use App\Jobs\DetectPlatformLinks;
use App\Models\PlatformLink;
use App\Models\Release;
use App\Models\Track;
use App\Services\Catalog\PlatformLinks;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The platform pages of releases and tracks: found on request, added
 * and corrected by hand.
 */
class PlatformLinkController extends Controller
{
    /**
     * What a page needs to show the links of a release or track.
     *
     * @return array{links: list<array<string, mixed>>, detecting: bool, platforms: list<array{value: string, label: string}>}
     */
    public static function props(Release|Track $record): array
    {
        $order = array_flip(array_keys(PlatformLinks::PLATFORMS));
        $links = PlatformLink::query()->where($record instanceof Release ? 'release_id' : 'track_id', $record->id)->whereNull('dismissed_at')->get()
            ->sortBy(fn (PlatformLink $link): array => [$order[$link->platform] ?? 999, $link->id]);

        return [
            'links' => array_values($links->map(fn (PlatformLink $link): array => [
                ...$link->only(['id', 'platform', 'url', 'origin']),
                'platform_name' => PlatformLinks::nameOf($link->platform),
            ])->all()),
            'detecting' => PlatformLinks::isDetecting($record),
            'platforms' => [
                ...collect(PlatformLinks::PLATFORMS)->map(fn (array $platform, string $key): array => ['value' => $key, 'label' => $platform['name']])->values()->all(),
                ['value' => PlatformLinks::OTHER, 'label' => 'Other'],
            ],
        ];
    }

    public function store(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->ownerRules($currentLabel),
            ...$this->linkRules(),
        ]);

        $column = isset($validated['release_id']) ? 'release_id' : 'track_id';
        $url = trim($validated['url']);

        $existing = PlatformLink::query()->where($column, $validated[$column])->where('url', $url)->first();

        if ($existing !== null && $existing->dismissed_at === null) {
            throw ValidationException::withMessages(['url' => __('This link is listed already.')]);
        }

        // Adding a link that was found and removed before brings it back as the user's own.
        $existing?->delete();

        PlatformLink::query()->create([
            $column => $validated[$column],
            'url' => $url,
            'platform' => $validated['platform'] ?? PlatformLinks::platformOf($url),
            'origin' => PlatformLink::MANUAL,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link added.')]);

        return back();
    }

    public function update(Request $request, PlatformLink $platformLink): RedirectResponse
    {
        $validated = $request->validate($this->linkRules());
        $url = trim($validated['url']);

        // A corrected link is the user's from then on.
        $platformLink->update(['url' => $url, 'platform' => $validated['platform'] ?? PlatformLinks::platformOf($url), 'origin' => PlatformLink::MANUAL]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link saved.')]);

        return back();
    }

    /**
     * Remove a link. One that was found is remembered, so the next
     * search does not bring it back.
     */
    public function destroy(PlatformLink $platformLink): RedirectResponse
    {
        $platformLink->origin === PlatformLink::MANUAL ? $platformLink->delete() : $platformLink->update(['dismissed_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link removed.')]);

        return back();
    }

    /**
     * Look for the pages of a release and its tracks, or of one track, in the background.
     */
    public function detect(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $validated = $request->validate($this->ownerRules($currentLabel));

        $record = isset($validated['release_id'])
            ? Release::query()->whereKey($validated['release_id'])->firstOrFail()
            : Track::query()->whereKey($validated['track_id'])->firstOrFail();

        PlatformLinks::mark($record, true);
        DetectPlatformLinks::dispatch($validated['release_id'] ?? null, $validated['track_id'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Looking for links. They appear here as they are found.')]);

        return back();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function ownerRules(CurrentLabel $currentLabel): array
    {
        return [
            'release_id' => ['nullable', 'required_without:track_id', 'prohibits:track_id', 'integer', Rule::exists('releases', 'id')->where('label_id', $currentLabel->id())],
            'track_id' => ['nullable', 'required_without:release_id', 'integer', Rule::exists('tracks', 'id')->where('label_id', $currentLabel->id())],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function linkRules(): array
    {
        return [
            'url' => ['required', 'string', 'max:1000', 'url:https,http'],
            'platform' => ['nullable', 'string', Rule::in([...array_keys(PlatformLinks::PLATFORMS), PlatformLinks::OTHER])],
        ];
    }
}
