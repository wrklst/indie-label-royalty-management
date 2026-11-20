<?php

namespace App\Support;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Uploads that go from the browser straight into the bucket instead of
 * through this server. A file lands under "uploads/" first and is moved
 * to its place when the browser reports it; what is never reported is
 * removed after a day.
 */
final class DirectUploads
{
    /** How long a browser has to send a file after asking where to, in minutes. */
    private const int LINK_MINUTES = 120;

    /**
     * Where the browser may send one file, or null when files are kept on this server.
     *
     * @return array{url: string, headers: array<string, string>, key: string}|null
     */
    public static function sign(int $labelId, string $name): ?array
    {
        $disk = WorkingCopy::disk();

        if (WorkingCopy::isLocal($disk)) {
            return null;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $key = "uploads/{$labelId}/".Str::uuid().(preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? ".{$extension}" : '');
        $signed = Storage::disk($disk)->temporaryUploadUrl($key, now()->addMinutes(self::LINK_MINUTES));

        $headers = [];

        foreach ((array) $signed['headers'] as $header => $value) {
            // A browser sets the host itself and refuses to be told.
            if (strtolower((string) $header) !== 'host') {
                $headers[(string) $header] = implode(', ', (array) $value);
            }
        }

        return ['url' => (string) $signed['url'], 'headers' => $headers, 'key' => $key];
    }

    /**
     * Whether the key is one this label was given to upload to.
     */
    public static function owns(string $key, int $labelId): bool
    {
        return preg_match('/^uploads\/'.$labelId.'\/[0-9a-f-]{36}(\.[a-z0-9]{1,10})?$/', $key) === 1;
    }

    /**
     * Whether the bucket takes uploads from pages served at $origin.
     * Null when files are kept on this server or the bucket does not say.
     */
    public static function allowed(string $origin): ?bool
    {
        try {
            $rules = self::rules();
        } catch (Throwable) {
            return null;
        }

        foreach ($rules as $rule) {
            if (array_intersect([$origin, '*'], $rule['AllowedOrigins'] ?? []) !== [] && in_array('PUT', $rule['AllowedMethods'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tell the bucket to take uploads from pages served at $origin. Rules
     * the bucket already has are kept.
     *
     * @throws Throwable with what the storage answered
     */
    public static function allow(string $origin): void
    {
        self::client()->putBucketCors([
            'Bucket' => self::bucket(),
            'CORSConfiguration' => ['CORSRules' => [...self::rules(), [
                'AllowedOrigins' => [$origin],
                'AllowedMethods' => ['PUT'],
                'AllowedHeaders' => ['*'],
                'MaxAgeSeconds' => 3600,
            ]]],
        ]);

        Cache::forget(self::cacheKey($origin));
    }

    /**
     * Where the answer of the bucket for $origin is remembered for a few minutes.
     */
    public static function cacheKey(string $origin): string
    {
        return 'direct-uploads:'.$origin;
    }

    /**
     * Remove uploads that were sent but never reported, such as those of a form that was not submitted.
     */
    public static function prune(): int
    {
        $disk = Storage::disk(WorkingCopy::disk());
        $before = now()->subDay()->getTimestamp();
        $stale = array_values(array_filter($disk->allFiles('uploads'), fn (string $path): bool => $disk->lastModified($path) < $before));

        $disk->delete($stale);

        return count($stale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rules(): array
    {
        try {
            return self::client()->getBucketCors(['Bucket' => self::bucket()])['CORSRules'] ?? [];
        } catch (S3Exception $exception) {
            if ($exception->getAwsErrorCode() === 'NoSuchCORSConfiguration') {
                return [];
            }

            throw $exception;
        }
    }

    private static function client(): S3Client
    {
        $disk = Storage::disk(WorkingCopy::disk());

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new RuntimeException('Files are not stored in a bucket.');
        }

        return $disk->getClient();
    }

    private static function bucket(): string
    {
        return (string) config('filesystems.disks.'.WorkingCopy::disk().'.bucket');
    }
}
