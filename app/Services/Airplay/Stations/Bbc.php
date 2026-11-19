<?php

namespace App\Services\Airplay\Stations;

/**
 * The BBC's radio stations share one website. Its programme pages list
 * the tracks that were played and are titled "BBC Radio 6 Music - Show, Episode".
 */
class Bbc extends StationDriver
{
    public function name(): string
    {
        return 'BBC';
    }

    public function region(): string
    {
        return 'GB';
    }

    public function domains(): array
    {
        return ['bbc.co.uk', 'bbc.com'];
    }

    public function searchesEveryTrack(): bool
    {
        return true;
    }

    public function describe(string $pageTitle, string $pageText): array
    {
        if (preg_match('/^(BBC (?:Radio|Asian|World|Sounds)[^-–|]*?)\s+[-–]\s+(.+)$/u', trim($pageTitle), $parts) !== 1) {
            return [];
        }

        return ['station' => trim($parts[1]), 'show' => trim($parts[2])];
    }
}
