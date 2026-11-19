<?php

namespace App\Services\Airplay\Stations;

/**
 * A station that needs no code of its own: a name, a country and its websites.
 */
final class SimpleStation extends StationDriver
{
    /**
     * @param  list<string>  $domains
     * @param  list<string>  $aliases
     */
    public function __construct(
        private readonly string $name,
        private readonly string $region,
        private readonly array $domains,
        private readonly array $aliases = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function region(): string
    {
        return $this->region;
    }

    public function domains(): array
    {
        return $this->domains;
    }

    public function aliases(): array
    {
        return $this->aliases;
    }
}
