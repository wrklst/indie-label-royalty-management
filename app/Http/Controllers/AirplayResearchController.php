<?php

namespace App\Http\Controllers;

use App\Enums\AirplayOrigin;
use App\Jobs\ResearchAirplayEntry;
use App\Jobs\ResearchTrackAirplay;
use App\Models\Airplay;
use App\Models\AirplayFinding;
use App\Models\Release;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\ResearchState;
use App\Services\Airplay\Stations\StationRegistry;
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
            throw ValidationException::withMessages(['scope' => __(WebSearch::NOT_SET_UP)]);
        }

        if ($selection['scope'] === 'release') {
            // Only a release of the current label is found.
            Release::query()->findOrFail($selection['release_id']);
        }

        $trackIds = $research->tracks($selection)->pluck('id');

        if ($trackIds->isEmpty()) {
            throw ValidationException::withMessages(['scope' => __('There are no tracks to research in this selection.')]);
        }

        Track::query()->whereIn('id', $trackIds)->update(['research_state' => ResearchState::QUEUED, 'research_step' => null, 'research_state_at' => now()]);
        $trackIds->each(fn (int $trackId) => ResearchTrackAirplay::dispatch($trackId));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count track queued for research.|:count tracks queued for research.', $trackIds->count()).' '.__('Findings appear here as they come in.')]);

        return back();
    }

    /**
     * Queue a closer look at one play: the show it was in and what was written about it.
     */
    public function airplay(Airplay $airplay, WebSearch $search): RedirectResponse
    {
        if (! $search->isConfigured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(WebSearch::NOT_SET_UP)]);

            return back();
        }

        ResearchState::set($airplay, ResearchState::QUEUED);
        ResearchAirplayEntry::dispatch($airplay->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Research queued. Findings for this play appear here in a few minutes.')]);

        return back();
    }

    /**
     * Keep a finding: as a link of the play it was searched for, or as a new play.
     */
    public function keep(AirplayFinding $finding, StationRegistry $stations): RedirectResponse
    {
        DB::transaction(function () use ($finding, $stations): void {
            $airplay = $finding->airplay;

            if ($airplay === null) {
                $track = Track::query()->with(['artist:id,name', 'artists:id,name'])->findOrFail($finding->track_id);

                $airplay = new Airplay([
                    'origin' => AirplayOrigin::Search,
                    'track_id' => $track->id,
                    'track_name' => $track->name,
                    'artist_name' => ArtistCredits::display($track),
                    'isrc' => $track->isrc,
                    'station' => $finding->station ?? $finding->domain,
                    'region' => $stations->forName($finding->station)?->region(),
                ]);
            }

            $airplay->addLink([
                'url' => $finding->url,
                'title' => $finding->page_title ?? $finding->title,
                'description' => trim(($finding->show === null ? '' : __('Show: :show.', ['show' => $finding->show]).' ').($finding->excerpt ?? $finding->description)) ?: null,
            ], AirplayOrigin::Search);
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
