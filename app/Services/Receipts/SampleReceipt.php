<?php

namespace App\Services\Receipts;

use RuntimeException;

/**
 * An invented receipt as a picture, to see what a model makes of one
 * before real receipts are sent to it.
 */
class SampleReceipt
{
    /** What a model that reads the picture correctly reports. */
    public const array EXPECTED = ['vendor' => 'Example Hosting GmbH', 'date' => '2026-03-14', 'total' => '49.90', 'currency' => 'EUR'];

    private const array LINES = [
        'Example Hosting GmbH', 'Musterweg 1, 10115 Berlin', '', 'INVOICE 2026-0314', 'Invoice date: 14 March 2026', '',
        'Web hosting, March 2026          41.93', 'VAT 19%                           7.97', '', 'TOTAL EUR                        49.90',
    ];

    public static function isAvailable(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    /**
     * Draw the receipt into a PNG file the caller removes.
     *
     * @throws RuntimeException when the picture cannot be made
     */
    public static function picture(): string
    {
        // The built-in letters are nine pixels wide; every line has to fit with a margin on both sides.
        $small = imagecreatetruecolor(24 + 9 * max(array_map(strlen(...), self::LINES)), 20 + 18 * count(self::LINES));
        $path = tempnam(sys_get_temp_dir(), 'sample-receipt');

        if ($small === false || $path === false) {
            throw new RuntimeException('The sample receipt could not be drawn.');
        }

        imagefill($small, 0, 0, (int) imagecolorallocate($small, 255, 255, 255));

        foreach (self::LINES as $index => $line) {
            imagestring($small, 5, 12, 10 + 18 * $index, $line, (int) imagecolorallocate($small, 20, 20, 20));
        }

        // Three times the size: the built-in letters are too small to read otherwise.
        $large = imagescale($small, imagesx($small) * 3, -1, IMG_NEAREST_NEIGHBOUR);

        if ($large === false || ! imagepng($large, $path)) {
            throw new RuntimeException('The sample receipt could not be drawn.');
        }

        return $path;
    }

    /**
     * Which of the expected fields a reading got wrong.
     *
     * @param  array<string, mixed>  $read
     * @return list<string>
     */
    public static function missed(array $read): array
    {
        $missed = [];

        foreach (self::EXPECTED as $field => $expected) {
            $value = trim((string) (is_scalar($read[$field] ?? null) ? $read[$field] : ''));

            $right = match ($field) {
                'vendor' => str_contains(mb_strtolower($value), 'example hosting'),
                // A decimal comma is how the amount is written in much of Europe, not a misreading.
                'total' => is_numeric(str_replace(',', '.', $value)) && round((float) str_replace(',', '.', $value), 2) === (float) $expected,
                default => mb_strtoupper($value) === $expected,
            };

            if (! $right) {
                $missed[] = $field;
            }
        }

        return $missed;
    }
}
