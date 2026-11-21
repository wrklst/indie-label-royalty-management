<?php

namespace App\Support;

use App\Services\Catalog\PlatformLinks;
use Illuminate\Support\Str;

/**
 * One name for each shop and streaming service, whatever a statement
 * calls it, and one word for what a format is: a stream, a download,
 * a record sold.
 */
class Platforms
{
    /**
     * Platforms that sell or play music but have no page to link to, besides those of PlatformLinks.
     *
     * @var array<string, string>
     */
    private const array FURTHER = [
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook and Instagram',
        'snapchat' => 'Snapchat',
        'netease' => 'NetEase',
        'tencent' => 'Tencent',
    ];

    /**
     * What a statement may call a platform, in lower case: the first one found in the name decides.
     *
     * @var array<string, string>
     */
    private const array SPELLINGS = [
        'spotify' => 'spotify', 'itunes' => 'apple_music', 'apple' => 'apple_music', 'youtube music' => 'youtube_music',
        'youtube' => 'youtube', 'amazon' => 'amazon_music', 'tidal' => 'tidal', 'deezer' => 'deezer',
        'soundcloud' => 'soundcloud', 'beatport' => 'beatport', 'bandcamp' => 'bandcamp', 'subvert' => 'subvert',
        'qobuz' => 'qobuz', 'juno' => 'juno', 'traxsource' => 'traxsource', 'boomkat' => 'boomkat', 'bleep' => 'bleep',
        'pandora' => 'pandora', 'napster' => 'napster', 'anghami' => 'anghami', 'tiktok' => 'tiktok',
        'facebook' => 'facebook', 'instagram' => 'facebook', 'snap' => 'snapchat', 'netease' => 'netease',
        'tencent' => 'tencent', 'qqmusic' => 'tencent', 'qq music' => 'tencent', 'kugou' => 'tencent', 'kuwo' => 'tencent',
    ];

    public const string STREAMS = 'streams';

    public const string DOWNLOADS = 'downloads';

    public const string CREATIONS = 'creations';

    public const string PHYSICAL = 'physical';

    public const string RIGHTS = 'rights';

    public const string OTHER = 'other';

    /** @var array<string, string> */
    public const array KINDS = [
        self::STREAMS => 'Streams',
        self::DOWNLOADS => 'Downloads',
        self::CREATIONS => 'Use in videos',
        self::PHYSICAL => 'Records and merchandise',
        self::RIGHTS => 'Rights and licences',
        self::OTHER => 'Other',
    ];

    /**
     * Every platform by key, in a fixed order: a platform keeps its
     * place, and with it its colour in every chart.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        return [
            ...array_map(fn (array $platform): string => $platform['name'], array_diff_key(PlatformLinks::PLATFORMS, ['songlink' => true, 'discogs' => true])),
            ...self::FURTHER,
        ];
    }

    /**
     * The platform a statement means by a retailer. One it does not know
     * stays what the statement calls it.
     *
     * @return array{key: string, name: string}
     */
    public static function of(?string $retailer): array
    {
        $retailer = trim((string) $retailer);

        if ($retailer === '') {
            return ['key' => 'unknown', 'name' => 'Unknown'];
        }

        $lower = Str::lower($retailer);

        foreach (self::SPELLINGS as $spelling => $key) {
            if (Str::contains($lower, $spelling)) {
                return ['key' => $key, 'name' => self::names()[$key]];
            }
        }

        return ['key' => 'x-'.Str::slug($retailer), 'name' => $retailer];
    }

    /**
     * What a format is, as one of the KINDS.
     */
    public static function kind(?string $format): string
    {
        $lower = Str::lower((string) $format);

        return match (true) {
            Str::contains($lower, ['creation', 'user generated']) => self::CREATIONS,
            Str::contains($lower, ['stream', 'cloud', 'radio', 'background']) => self::STREAMS,
            Str::contains($lower, 'download') => self::DOWNLOADS,
            Formats::group($format) === 'Physical' => self::PHYSICAL,
            in_array(Formats::group($format), ['Rights', 'Publishing'], true) => self::RIGHTS,
            default => self::OTHER,
        };
    }
}
