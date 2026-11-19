<?php

namespace App\Services\Releases;

use App\Models\ReleaseFile;
use App\Support\WorkingCopy;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Small copies of the images of a release, so lists do not load covers
 * of several megabytes.
 *
 * A variant is made with GD the first time it is asked for and kept on
 * the same private disk as its source, in a "thumbs" folder next to it.
 * Variants are always JPEG on a white background: covers are opaque, and
 * one format keeps the cache and the response headers simple. The file
 * name carries a fingerprint of the source, so a changed source gets a
 * new variant and the outdated one is removed.
 */
class ReleaseImageVariants
{
    /**
     * Images with more pixels are not decoded (a decompression bomb
     * guard). 40 megapixels is a square of about 6300 pixels, twice the
     * edge stores ask for; decoding one takes up to half a gigabyte.
     */
    public const int MAX_PIXELS = 40_000_000;

    private const int JPEG_QUALITY = 82;

    private const int MEMORY_HEADROOM = 32 * 1024 * 1024;

    /**
     * The path on the file's disk of the variant, made now if it is not
     * there yet. Null when no variant can be made (not an image, missing,
     * corrupt, of a type GD cannot read, or GD is not installed): the
     * caller serves the original.
     *
     * @throws ImageTooLarge when the image has too many pixels to decode safely
     */
    public function path(ReleaseFile $file, ReleaseImageSize $size): ?string
    {
        $disk = Storage::disk($file->disk);

        if (! extension_loaded('gd') || ! $file->isImage() || ! $disk->exists($file->path)) {
            return null;
        }

        $variant = $this->directory($file).'/'.$this->name($file, $size, $disk);

        if ($disk->exists($variant)) {
            return $variant;
        }

        $copy = WorkingCopy::of($file->disk, $file->path);
        $source = $copy->path;

        $dimensions = @getimagesize($source);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return null;
        }

        $pixels = $dimensions[0] * $dimensions[1];

        if ($pixels > self::MAX_PIXELS || ! $this->reserveMemory($pixels * $this->bytesPerPixel($dimensions[2], $dimensions['bits'] ?? 8))) {
            throw new ImageTooLarge("Image {$file->id} is too large to resize.");
        }

        $image = $this->decode($source, $dimensions[2]);

        if ($image === null) {
            return null;
        }

        $resized = $this->orient($this->resize($image, $size), $this->orientation($source, $dimensions[2]));

        $this->forget($file, $size);
        $disk->makeDirectory($this->directory($file));

        // Written under a temporary name first so a request arriving in
        // the meantime never reads half a file.
        $temporary = WorkingCopy::scratch($file->disk, $variant);

        if (! imagejpeg($resized, $temporary, self::JPEG_QUALITY) || ! WorkingCopy::publish($file->disk, $variant, $temporary)) {
            @unlink($temporary);

            return null;
        }

        return $variant;
    }

    /**
     * Remove the stored variants of a file: all of them, or those of one size.
     */
    public function forget(ReleaseFile $file, ?ReleaseImageSize $size = null): void
    {
        $disk = Storage::disk($file->disk);
        $prefix = $file->id.'-'.($size === null ? '' : $size->value.'-');

        $disk->delete(array_values(array_filter(
            $this->stored($disk, $this->directory($file)),
            fn (string $path): bool => str_starts_with(basename($path), $prefix),
        )));
    }

    private function directory(ReleaseFile $file): string
    {
        return trim(dirname($file->path), './').'/thumbs';
    }

    /**
     * "{file id}-{size}-{fingerprint}.jpg". The fingerprint covers the
     * recorded checksum and the size and modification time of the stored
     * file, so a replaced source is noticed without reading it.
     */
    private function name(ReleaseFile $file, ReleaseImageSize $size, Filesystem $disk): string
    {
        $fingerprint = substr(hash('sha256', implode('|', [$file->checksum, $disk->size($file->path), $disk->lastModified($file->path)])), 0, 16);

        return "{$file->id}-{$size->value}-{$fingerprint}.jpg";
    }

    /**
     * @return list<string>
     */
    private function stored(Filesystem $disk, string $directory): array
    {
        return $disk->exists($directory) ? array_values($disk->files($directory)) : [];
    }

    /**
     * Make sure the request may use the memory decoding takes, raising
     * the limit for this request when it is lower.
     */
    private function reserveMemory(int $bytes): bool
    {
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));
        $needed = memory_get_usage() + $bytes + self::MEMORY_HEADROOM;

        return $limit <= 0 || $needed <= $limit || ini_set('memory_limit', (string) $needed) !== false;
    }

    /**
     * What decoding takes per pixel: GD keeps four bytes, and for every
     * format but JPEG (which is read line by line) the decoder first
     * holds the whole picture as well, at up to four channels of one or
     * two bytes each.
     */
    private function bytesPerPixel(int $type, int $bits): int
    {
        return $type === IMAGETYPE_JPEG ? 5 : 4 + 4 * ($bits > 8 ? 2 : 1);
    }

    private function decode(string $source, int $type): ?GdImage
    {
        $decoder = match ($type) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            default => null,
        };

        if ($decoder === null || ! function_exists($decoder)) {
            return null;
        }

        try {
            $image = $decoder($source);
        } catch (Throwable) {
            return null;
        }

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * Scale the image down (never up) onto a white canvas: cut to a
     * centred square for a cropped size, fitted into the box otherwise.
     */
    private function resize(GdImage $image, ReleaseImageSize $size): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $box = $size->pixels();

        if ($size->isCropped()) {
            $side = min($width, $height);
            [$sourceX, $sourceY, $sourceWidth, $sourceHeight] = [intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side];
            $targetWidth = $targetHeight = max(1, min($box, $side));
        } else {
            [$sourceX, $sourceY, $sourceWidth, $sourceHeight] = [0, 0, $width, $height];
            $scale = min(1, $box / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $canvas;
    }

    /**
     * The EXIF orientation of a JPEG (1 when there is none to honour).
     */
    private function orientation(string $source, int $type): int
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 1;
        }

        try {
            $exif = @exif_read_data($source);
        } catch (Throwable) {
            return 1;
        }

        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return is_int($orientation) ? $orientation : 1;
    }

    /**
     * Turn the image the way its EXIF orientation says it is meant to
     * be seen. Done after scaling, so only the small copy is rotated.
     */
    private function orient(GdImage $image, int $orientation): GdImage
    {
        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($degrees !== 0) {
            $rotated = imagerotate($image, $degrees, 0);
            $image = $rotated instanceof GdImage ? $rotated : $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }
}
