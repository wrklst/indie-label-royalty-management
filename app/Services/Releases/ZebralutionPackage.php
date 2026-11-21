<?php

namespace App\Services\Releases;

use App\Models\Release;
use App\Models\Track;
use App\Support\WorkingCopy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One release prepared for Zebralution: its metadata sheet filled in,
 * the cover, and the WAV masters in a folder named by the EAN, each as
 * "set-track title", which is what its FTP server asks for.
 *
 * Zebralution takes no archives: the ZIP is unpacked, the folder goes
 * to the FTP server and the sheet and the cover go by email.
 *
 * @see "Content Preparation and Submission – AUDIO (Music)", V2.19Z Build 2025
 */
class ZebralutionPackage extends SubvertPackage
{
    public const ?int MAX_BYTES = null;

    public const ?int MAX_COVER_BYTES = null;

    /** "A minimum size of 3000 x 3000 pixels." */
    public const int COVER_PIXELS = 3000;

    protected const string STORE = 'Zebralution';

    protected const string ACCEPTS = 'WAV with 16 bit, 44.1 kHz, stereo';

    protected const array LOSSLESS = ['wav'];

    protected const array LOSSY = [];

    public function __construct(private readonly ZebralutionSheet $sheet) {}

    public function fileName(Release $release): string
    {
        return SafeFileName::from($release->name.($release->version ? " ({$release->version})" : '').' - Zebralution').'.zip';
    }

    /**
     * @return list<string>
     */
    public function problems(Release $release): array
    {
        $problems = [...parent::problems($release), ...$this->sheet->problems($release, $this->tracks($release))];
        $cover = $this->cover($release);

        if ($cover === null || ! Storage::disk($cover->disk)->exists($cover->path)) {
            return $problems;
        }

        if (! in_array($this->extension($cover), ['jpg', 'jpeg'], true)) {
            $problems[] = "The cover “{$cover->original_name}” is not a JPEG, which is the only format Zebralution takes.";
        }

        [$width, $height] = @getimagesize(WorkingCopy::of($cover->disk, $cover->path)->path) ?: [0, 0];

        if ($width !== $height || $width < self::COVER_PIXELS) {
            $problems[] = sprintf('The cover is %d × %d pixels; Zebralution takes square covers of at least %3$d × %3$d.', $width, $height, self::COVER_PIXELS);
        }

        return $problems;
    }

    protected function coverName(Release $release): string
    {
        return ZebralutionSheet::ean($release);
    }

    /**
     * "0828768767023/1-2 Second Track": a folder per product, and
     * nothing in a name but the characters Zebralution allows.
     */
    protected function audioName(Release $release, Track $track, int $number, int $width, bool $severalDiscs): string
    {
        $title = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z0-9.,;_+\-#&!()\[\] ]/', ' ', Str::ascii($track->name.($track->version ? " ({$track->version})" : '')))), ' .');

        return sprintf('%s/%d-%d %s', ZebralutionSheet::ean($release), $track->disc_number ?? 1, $number, $title === '' ? 'Track' : $title);
    }

    protected function sheets(Release $release): array
    {
        $tracks = $this->tracks($release);

        return ['Zebralution_Metadata_'.ZebralutionSheet::ean($release).'.xlsx' => $this->sheet->workbook($this->sheet->rows($release, $tracks, $this->numbers($tracks)))];
    }
}
