<?php

namespace App\Support;

/**
 * The distributors this installation has something built for: an
 * importer for their statements, a delivery package, a link to their
 * analytics export. A label switches on the ones it works with under
 * Settings, and only theirs is shown.
 */
final class Distributors
{
    public const string ZEBRALUTION = 'zebralution';

    /**
     * Key => name. The key is also the source() of the distributor's importers.
     * ponytail: one entry; a distributor is added here together with the first thing built for it.
     *
     * @var array<string, string>
     */
    public const array NAMES = [
        self::ZEBRALUTION => 'Zebralution',
    ];
}
