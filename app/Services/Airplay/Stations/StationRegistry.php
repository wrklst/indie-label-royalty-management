<?php

namespace App\Services\Airplay\Stations;

/**
 * Every station the research knows. Add a station here: a class of its
 * own when it has pages worth reading, a SimpleStation otherwise.
 */
class StationRegistry
{
    /**
     * @return list<StationDriver>
     */
    public function all(): array
    {
        return [
            new Bbc,
            new Bayern2,
            new SimpleStation('FluxFM', 'DE', ['fluxfm.de'], ['Flux FM']),
            new SimpleStation('hr4', 'DE', ['hr4.de']),
            new SimpleStation('radioeins', 'DE', ['radioeins.de'], ['Radio Eins']),
            new SimpleStation('Deutschlandfunk Nova', 'DE', ['deutschlandfunknova.de'], ['DLF Nova']),
            new SimpleStation('ByteFM', 'DE', ['byte.fm']),
            new SimpleStation('Refuge Worldwide', 'DE', ['refugeworldwide.com']),
            new SimpleStation('FM4', 'AT', ['fm4.orf.at']),
            new SimpleStation('NTS', 'GB', ['nts.live'], ['NTS Radio']),
            new SimpleStation('Rinse FM', 'GB', ['rinse.fm']),
            new SimpleStation('Worldwide FM', 'GB', ['worldwidefm.net']),
            new SimpleStation('KEXP', 'US', ['kexp.org']),
            new SimpleStation('KCRW', 'US', ['kcrw.com']),
            new SimpleStation('triple j', 'AU', ['abc.net.au'], ['Triple J']),
        ];
    }

    public function forDomain(string $domain): ?StationDriver
    {
        return array_find($this->all(), fn (StationDriver $driver): bool => $driver->owns($domain));
    }

    public function forName(?string $station): ?StationDriver
    {
        return $station === null ? null : array_find($this->all(), fn (StationDriver $driver): bool => $driver->isCalled($station));
    }
}
