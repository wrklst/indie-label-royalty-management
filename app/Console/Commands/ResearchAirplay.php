<?php

namespace App\Console\Commands;

use App\Jobs\ResearchTrackAirplay;
use App\Models\Label;
use App\Models\Track;
use App\Services\Airplay\AirplayResearch;
use App\Services\Airplay\WebSearch;
use App\Support\CurrentLabel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('royalties:research-airplay
    {--label= : Slug or id of one label. Without it every label is researched}
    {--days=31 : Research tracks whose last research is older than this many days}
    {--limit=10 : The most tracks to queue per label; every track costs up to three billed searches}')]
#[Description('Queue a web research for tracks that have not been researched lately')]
class ResearchAirplay extends Command
{
    public function handle(AirplayResearch $research, WebSearch $search, CurrentLabel $currentLabel): int
    {
        if (! $search->isConfigured()) {
            $this->warn('Web search is not set up: DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD are missing.');

            return self::SUCCESS;
        }

        $currentLabel->set(null);
        $labels = Label::query()->when($this->option('label') !== null, fn ($query) => $query
            ->where(ctype_digit((string) $this->option('label')) ? 'id' : 'slug', $this->option('label')))->get();

        if ($labels->isEmpty()) {
            $this->error('Label not found. Pass --label with a slug or id.');

            return self::FAILURE;
        }

        foreach ($labels as $label) {
            $trackIds = $research->stale(Track::query()->where('label_id', $label->id), (int) $this->option('days'))
                ->orderByRaw('researched_at is null desc')->orderBy('researched_at')->orderBy('id')
                ->limit((int) $this->option('limit'))->pluck('id');

            $trackIds->each(fn (int $trackId) => ResearchTrackAirplay::dispatch($trackId));

            $this->info("{$label->name}: {$trackIds->count()} track(s) queued.");
        }

        return self::SUCCESS;
    }
}
