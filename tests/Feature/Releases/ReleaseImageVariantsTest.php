<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    $this->label = Label::factory()->create();
    $this->release = Release::factory()->create(['label_id' => $this->label->id]);
    $this->actingAs(labelAdmin($this->label));
});

/**
 * The bytes of an image whose left half is red and whose right half is blue.
 */
function twoColourImage(int $width, int $height, string $format = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));

    ob_start();
    $format === 'png' ? imagepng($image) : imagejpeg($image, null, 95);

    return (string) ob_get_clean();
}

function storedImage(Release $release, string $contents, array $attributes = []): ReleaseFile
{
    $file = ReleaseFile::factory()->create([
        'label_id' => $release->label_id, 'release_id' => $release->id, 'kind' => 'cover', 'mime_type' => 'image/jpeg',
        'original_name' => 'Front cover.jpg', 'path' => "releases/{$release->label_id}/{$release->id}/".fake()->uuid().'.jpg',
        'checksum' => hash('sha256', $contents), ...$attributes,
    ]);
    Storage::disk('local')->put($file->path, $contents);

    return $file;
}

function variantPath(TestResponse $response): string
{
    return $response->baseResponse->getFile()->getPathname();
}

/**
 * @return array{red: int, green: int, blue: int, alpha: int}
 */
function pixelAt(string $path, int $x, int $y): array
{
    $image = imagecreatefromjpeg($path);

    return imagecolorsforindex($image, imagecolorat($image, $x, $y));
}

test('a thumbnail is a small square JPEG cut from the centre of the image', function () {
    $file = storedImage($this->release, twoColourImage(400, 200));

    $response = $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    [$width, $height, $type] = getimagesize(variantPath($response));
    $left = pixelAt(variantPath($response), 8, 48);
    $right = pixelAt(variantPath($response), 88, 48);

    expect([$width, $height, $type])->toBe([96, 96, IMAGETYPE_JPEG])
        ->and($left['red'])->toBeGreaterThan(200)
        ->and($left['blue'])->toBeLessThan(60)
        ->and($right['blue'])->toBeGreaterThan(200)
        ->and($right['red'])->toBeLessThan(60)
        ->and(filesize(variantPath($response)))->toBeLessThan(10_000);
});

test('the medium size keeps the whole image and its proportions', function () {
    $file = storedImage($this->release, twoColourImage(1200, 800));

    $response = $this->get(route('release-files.show', [$file->id, 'size' => 'medium']))->assertOk();

    expect(array_slice(getimagesize(variantPath($response)), 0, 2))->toBe([600, 400]);
});

test('a small image is never enlarged', function (string $size, array $expected) {
    $file = storedImage($this->release, twoColourImage(50, 40));

    $response = $this->get(route('release-files.show', [$file->id, 'size' => $size]))->assertOk();

    expect(array_slice(getimagesize(variantPath($response)), 0, 2))->toBe($expected);
})->with([
    'thumb' => ['thumb', [40, 40]],
    'medium' => ['medium', [50, 40]],
]);

test('transparent areas of a PNG become white in the JPEG variant', function () {
    $image = imagecreatetruecolor(200, 200);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 100, 0, 199, 199, imagecolorallocatealpha($image, 0, 0, 255, 0));
    ob_start();
    imagepng($image);
    $file = storedImage($this->release, (string) ob_get_clean(), ['mime_type' => 'image/png', 'original_name' => 'front.png']);

    $response = $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $transparent = pixelAt(variantPath($response), 10, 48);
    $opaque = pixelAt(variantPath($response), 86, 48);

    expect(min($transparent['red'], $transparent['green'], $transparent['blue']))->toBeGreaterThan(240)
        ->and($opaque['blue'])->toBeGreaterThan(200)
        ->and($opaque['red'])->toBeLessThan(60)
        ->and($response->headers->get('Content-Disposition'))->toContain('front.jpg');
});

test('a JPEG is turned the way its EXIF orientation says', function () {
    // An APP1 segment that says "rotate 90° clockwise to view" (orientation 6).
    $exif = "Exif\0\0MM\0*\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0";
    $jpeg = twoColourImage(400, 200);
    $file = storedImage($this->release, substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));

    $response = $this->get(route('release-files.show', [$file->id, 'size' => 'medium']))->assertOk();

    $top = pixelAt(variantPath($response), 100, 20);
    $bottom = pixelAt(variantPath($response), 100, 380);

    expect(array_slice(getimagesize(variantPath($response)), 0, 2))->toBe([200, 400])
        ->and($top['red'])->toBeGreaterThan(200)
        ->and($bottom['blue'])->toBeGreaterThan(200);
})->skip(! function_exists('exif_read_data'), 'The exif extension is not installed.');

test('a variant is made once and then served from the private disk', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));

    $first = $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))->assertOk();
    $path = variantPath($first);
    $bytes = file_get_contents($path);
    touch($path, 1_700_000_000);
    clearstatcache();
    $second = $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))->assertOk();
    clearstatcache();

    expect(variantPath($second))->toBe($path)
        ->and(filemtime($path))->toBe(1_700_000_000)
        ->and(file_get_contents($path))->toBe($bytes)
        ->and($path)->toStartWith(Storage::disk('local')->path("releases/{$this->label->id}/{$this->release->id}/thumbs/{$file->id}-thumb-"))
        ->and(Storage::disk('local')->files("releases/{$this->label->id}/{$this->release->id}/thumbs"))->toHaveCount(1);
});

test('a variant may be kept by the browser of the user but not by shared caches', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));

    $response = $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))->assertOk();
    $cacheControl = (string) $response->headers->get('Cache-Control');
    $etag = (string) $response->headers->get('ETag');

    expect($cacheControl)->toContain('private')->toContain('max-age=86400')->not->toContain('public')->not->toContain('no-store')
        ->and($etag)->not->toBe('');

    $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']), ['If-None-Match' => $etag])->assertStatus(304);
});

test('a variant is made again when its source changes and the outdated one is removed', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));
    $source = Storage::disk('local')->path($file->path);
    $old = variantPath($this->get(route('release-files.show', [$file->id, 'size' => 'medium']))->assertOk());

    file_put_contents($source, twoColourImage(900, 300));
    touch($source, time() + 60);
    clearstatcache();
    $new = variantPath($this->get(route('release-files.show', [$file->id, 'size' => 'medium']))->assertOk());

    expect($new)->not->toBe($old)
        ->and(file_exists($old))->toBeFalse()
        ->and(array_slice(getimagesize($new), 0, 2))->toBe([600, 200]);
});

test('the variants of an image are deleted with it', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));
    $other = storedImage($this->release, twoColourImage(300, 300), ['kind' => 'artwork']);
    $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))->assertOk();
    $this->get(route('release-files.show', [$file->id, 'size' => 'medium']))->assertOk();
    $kept = variantPath($this->get(route('release-files.show', [$other->id, 'size' => 'thumb']))->assertOk());

    $this->delete(route('release-files.destroy', $file->id))->assertRedirect();

    expect(array_map(fn (string $path): string => Storage::disk('local')->path($path), Storage::disk('local')->allFiles()))
        ->toEqualCanonicalizing([Storage::disk('local')->path($other->path), $kept]);
});

test('variants are out of reach for other labels, payees and guests', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));
    $url = route('release-files.show', [$file->id, 'size' => 'thumb']);
    $this->get($url)->assertOk();

    $this->actingAs(labelAdmin(Label::factory()->create()))->get($url)->assertNotFound();

    $payeeUser = User::factory()->create(['current_label_id' => $this->label->id]);
    $this->label->users()->attach($payeeUser, ['role' => LabelRole::Payee->value, 'payee_id' => Payee::factory()->create(['label_id' => $this->label->id])->id]);
    $this->actingAs($payeeUser)->get($url)->assertRedirect(route('portal.index'));

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

test('an unknown size is refused', function () {
    $file = storedImage($this->release, twoColourImage(300, 300));

    $this->get(route('release-files.show', [$file->id, 'size' => 'huge']))->assertSessionHasErrors('size');

    expect(Storage::disk('local')->allFiles())->toBe([$file->path]);
});

test('the original is sent when no variant applies', function (array $attributes, string $contents, array $query, string $contentType) {
    $file = storedImage($this->release, $contents, $attributes);

    $response = $this->get(route('release-files.show', [$file->id, ...$query]))->assertOk()->assertHeader('Content-Type', $contentType);

    expect(variantPath($response))->toBe(Storage::disk('local')->path($file->path))
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(Storage::disk('local')->allFiles())->toBe([$file->path]);
})->with([
    'a file that is not an image' => [['kind' => 'document', 'mime_type' => 'application/pdf', 'original_name' => 'kit.pdf'], '%PDF-1.4', ['size' => 'thumb'], 'application/pdf'],
    'an image that cannot be read' => [[], 'not really a jpeg', ['size' => 'thumb'], 'image/jpeg'],
    'an image cut off in the middle' => [[], substr(twoColourImage(300, 300, 'png'), 0, 120), ['size' => 'medium'], 'image/jpeg'],
    'a download' => [[], twoColourImage(300, 300), ['size' => 'thumb', 'download' => 1], 'application/octet-stream'],
]);

test('an image with too many pixels is not resized', function () {
    // Only a PNG header: it claims 10000 × 10000 pixels, which is all the guard looks at.
    $header = 'IHDR'.pack('NN', 10_000, 10_000)."\x08\x02\0\0\0";
    $file = storedImage($this->release, "\x89PNG\r\n\x1a\n".pack('N', 13).$header.pack('N', crc32($header)), ['mime_type' => 'image/png']);

    $this->get(route('release-files.show', [$file->id, 'size' => 'thumb']))->assertNotFound();

    expect(Storage::disk('local')->allFiles())->toBe([$file->path]);
});
