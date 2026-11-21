<?php

namespace App\Support;

/**
 * Everything a label can switch on: the services this installation has
 * something built for. Each entry says what kind of service it is, what
 * it is for and what is hidden for a label that does not work with it.
 * Nothing is hidden for a label that never chose. The key of a service
 * that has statements is the source() of its importers.
 */
final class Services
{
    public const string ZEBRALUTION = 'zebralution';

    public const string BANDCAMP = 'bandcamp';

    public const string SUBVERT = 'subvert';

    public const string RIGHTZ_AUDIO = 'rightz-audio';

    /**
     * ponytail: a service is added here together with the first thing built for it.
     *
     * @var array<string, array{name: string, kind: string, purpose: string, gates: string}>
     */
    public const array ALL = [
        self::ZEBRALUTION => [
            'name' => 'Zebralution',
            'kind' => 'Distributor',
            'purpose' => 'Delivers the label\'s releases to the platforms and sends statements and play counts.',
            'gates' => 'delivery ZIP, statement layouts, genre suggestions and play counts export link.',
        ],
        self::BANDCAMP => [
            'name' => 'Bandcamp',
            'kind' => 'Shop',
            'purpose' => 'Sells the label\'s music directly; its sales are imported from reports or fetched every week.',
            'gates' => 'delivery ZIP, statement layouts and weekly sales sync.',
        ],
        self::SUBVERT => [
            'name' => 'Subvert',
            'kind' => 'Shop',
            'purpose' => 'A cooperative shop that takes a release as one ZIP file.',
            'gates' => 'delivery ZIP.',
        ],
        self::RIGHTZ_AUDIO => [
            'name' => 'Rightz Audio',
            'kind' => 'Rights society',
            'purpose' => 'Collects neighbouring rights; its statements also tell where tracks were played.',
            'gates' => 'statement layouts.',
        ],
    ];

    public static function has(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
