<?php

namespace App\Services\Airplay\Stations;

/**
 * Bayern 2 publishes a playlist page per broadcast, titled
 * "Playlist: Host | Playlisten | Show | Bayern 2 | Radio".
 */
class Bayern2 extends StationDriver
{
    public function name(): string
    {
        return 'Bayern 2';
    }

    public function region(): string
    {
        return 'DE';
    }

    public function domains(): array
    {
        return ['br.de'];
    }

    public function aliases(): array
    {
        return ['BR Bayern 2', 'Bayern2'];
    }

    public function describe(string $pageTitle, string $pageText): array
    {
        $parts = array_map(trim(...), explode('|', $pageTitle));

        if (count($parts) < 4 || ! str_starts_with($parts[0], 'Playlist')) {
            return [];
        }

        $host = trim(substr($parts[0], strlen('Playlist')), ': ');

        return ['show' => $parts[2].($host === '' ? '' : " ({$host})")];
    }
}
