<?php

namespace App\Http\Controllers;

use App\Enums\AirplayOrigin;
use App\Jobs\ResearchTrackAirplay;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Release;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\WebSearch;
use App\Services\Releases\ArtistCredits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AirplayResearchController extends Controller
{
    /**
     * Queue a research for a group of tracks.
     */
    public function store(Request $request, AirplayResearch $research, WebSearch $search): RedirectResponse
    {
        $selection = $request->validate([
            'scope' => ['required', Rule::in(['stale', 'release', 'tracks', 'all'])],
            'release_id' => ['nullable', 'required_if:scope,release', 'integer'],
            'track_ids' => ['nullable', 'required_if:scope,tracks', 'array'],
            'track_ids.*' => ['integer'],
        ]);

        if (! $search->isConfigured()) {
            throw ValidationException::withMessages(['scope' => __('Web search is not set up: DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD are missing.')]);
        }

        if ($selection['scope'] === 'release') {
            // Only a release of the current label is found.
            Release::query()->findOrFail($selection['release_id']);
        }

        $trackIds = $research->tracks($selection)->pluck('id');

        if ($trackIds->isEmpty()) {
            throw ValidationException::withMessages(['scope' => __('There are no tracks to research in this selection.')]);
        }

        $trackIds->each(fn (int $trackId) => ResearchTrackAirplay::dispatch($trackId));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count track(s) queued for research. Findings appear here as they come in.', ['count' => $trackIds->count()])]);

        return back();
    }

    /**
     * Keep a finding: as a link of the play it was searched for, or as a new play.
     */
    public function keep(AirplayFinding $finding): RedirectResponse
    {
        DB::transaction(function () use ($finding): void {
            $track = Track::query()->with(['artist:id,name', 'artists:id,name'])->findOrFail($finding->track_id);

            $airplay = $finding->airplay ?? new Airplay([
                'origin' => AirplayOrigin::Search,
                'track_id' => $track->id,
                'track_name' => $track->name,
                'artist_name' => ArtistCredits::display($track),
                'isrc' => $track->isrc,
                'station' => $finding->station ?? $finding->domain ?? __('Unknown'),
            ]);

            $airplay->addLink($finding->only(['url', 'title', 'description']), AirplayOrigin::Search);
            $airplay->save();

            $finding->update(['status' => AirplayFinding::KEPT, 'airplay_id' => $airplay->id]);
        });

        return back();
    }

    public function dismiss(AirplayFinding $finding): RedirectResponse
    {
        $finding->update(['status' => AirplayFinding::DISMISSED]);

        return back();
    }
}
