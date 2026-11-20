<?php

namespace App\Services\Releases;

use App\Models\Release;

/**
 * One release prepared for Bandcamp: the cover, one lossless audio file
 * per track named so they sort in track order, and a text sheet to copy
 * titles, numbers and texts from.
 *
 * Bandcamp has no upload for a whole release: the ZIP is unpacked and
 * its files are added on Bandcamp's album page. It takes WAV, AIFF or
 * FLAC only. How large a track may be depends on the account, so size
 * is not checked here.
 *
 * @see https://get.bandcamp.help/en/articles/15263088-how-do-i-upload-music
 */
class BandcampPackage extends SubvertPackage
{
    public const ?int MAX_BYTES = null;

    public const ?int MAX_COVER_BYTES = null;

    protected const string STORE = 'Bandcamp';

    protected const string ACCEPTS = 'WAV, AIFF or FLAC';

    protected const array LOSSY = [];

    public function __construct(private readonly ReleasePackage $package) {}

    public function fileName(Release $release): string
    {
        return SafeFileName::from($release->name.($release->version ? " ({$release->version})" : '').' - Bandcamp').'.zip';
    }

    protected function sheets(Release $release): array
    {
        return ['release.txt' => $this->package->labelCopy($release)];
    }
}
