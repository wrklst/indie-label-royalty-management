<?php

namespace App\Services\Airplay\Stations;

/**
 * What the research knows about one broadcaster: how to recognise its
 * pages, how to search for a track there and how to read a show from one
 * of its pages.
 *
 * A new station is one class in this directory, listed in
 * StationRegistry. Only name(), region() and domains() are required;
 * override the rest where a station works differently. For a station
 * that needs nothing special, add a SimpleStation to the registry instead.
 */
abstract class StationDriver
{
    /**
     * The name shown for the station, e.g. "Bayern 2".
     */
    abstract public function name(): string;

    /**
     * ISO code of the country to search from.
     */
    abstract public function region(): string;

    /**
     * Websites of the station, without "www.": a finding on one of them belongs to it.
     *
     * @return list<string>
     */
    abstract public function domains(): array;

    /**
     * Other spellings statements and people use, e.g. "BR Bayern 2".
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return [];
    }

    /**
     * Whether every track research asks for this station. Each such
     * station is one more billed search per track, so keep them few.
     */
    public function searchesEveryTrack(): bool
    {
        return false;
    }

    /**
     * What to search for to find a track at this station.
     *
     * @return list<string>
     */
    public function queries(?string $artist, string $title): array
    {
        $subject = trim(($artist === null ? '' : "\"{$artist}\" ")."\"{$title}\"");

        return ["{$subject} {$this->name()}", ...($artist === null ? [] : ["\"{$artist}\" {$this->name()} playlist"])];
    }

    /**
     * Read what a page of the station says about the broadcast.
     *
     * @return array{station?: string, show?: string} The exact station of a group and the show, as far as the page tells.
     */
    public function describe(string $pageTitle, string $pageText): array
    {
        return [];
    }

    public function owns(string $domain): bool
    {
        $domain = mb_strtolower($domain);

        return array_any($this->domains(), fn (string $own): bool => $domain === $own || str_ends_with($domain, ".{$own}"));
    }

    /**
     * Whether a station name from a statement or typed by hand means this station.
     */
    public function isCalled(string $station): bool
    {
        $station = mb_strtolower($station);

        return array_any([$this->name(), ...$this->aliases()], fn (string $name): bool => str_contains($station, mb_strtolower($name)));
    }
}
