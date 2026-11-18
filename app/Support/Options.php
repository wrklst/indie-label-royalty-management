<?php

namespace App\Support;

use App\Models\Artist;
use App\Models\Contract;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Territory;
use App\Models\Track;

/**
 * Select box options for the forms of the current label.
 */
class Options
{
    /**
     * @param  class-string  $model
     * @return list<array{id: int, name: string}>
     */
    private static function named(string $model): array
    {
        return $model::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($record): array => ['id' => $record->id, 'name' => $record->name])->all();
    }

    /** @return list<array{id: int, name: string}> */
    public static function artists(): array
    {
        return self::named(Artist::class);
    }

    /** @return list<array{id: int, name: string}> */
    public static function contracts(): array
    {
        return self::named(Contract::class);
    }

    /** @return list<array{id: int, name: string}> */
    public static function payees(): array
    {
        return self::named(Payee::class);
    }

    /** @return list<array{id: int, name: string}> */
    public static function ledgers(): array
    {
        return self::named(Ledger::class);
    }

    /** @return list<array{id: int, name: string}> */
    public static function territories(): array
    {
        return self::named(Territory::class);
    }

    /** @return array<int, array{id: int, name: string}> */
    public static function releases(): array
    {
        return Release::query()->orderBy('name')->get(['id', 'name', 'catalog_number'])
            ->map(fn (Release $release): array => [
                'id' => $release->id,
                'name' => $release->catalog_number ? "{$release->name} [{$release->catalog_number}]" : $release->name,
            ])->all();
    }

    /** @return array<int, array{id: int, name: string, release_id: int}> */
    public static function tracks(): array
    {
        return Track::query()->orderBy('track_number')->orderBy('name')->get(['id', 'name', 'release_id', 'isrc'])
            ->map(fn (Track $track): array => [
                'id' => $track->id,
                'name' => $track->isrc ? "{$track->name} ({$track->isrc})" : $track->name,
                'release_id' => $track->release_id,
            ])->all();
    }
}
