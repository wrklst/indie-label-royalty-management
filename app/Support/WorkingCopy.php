<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A stored file as a path on this machine, for tools that only read
 * paths: ffmpeg, GD, the zip library, the statement readers. On the
 * local disk that is the file itself; from a bucket it is a temporary
 * download, removed when the object is no longer used.
 */
final class WorkingCopy
{
    private function __construct(public readonly string $path, private readonly bool $temporary) {}

    public function __destruct()
    {
        if ($this->temporary) {
            @unlink($this->path);
        }
    }

    /**
     * The disk new files are stored on.
     */
    public static function disk(): string
    {
        return (string) config('royalties.storage.disk', 'local');
    }

    public static function isLocal(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    /**
     * @throws RuntimeException when the file cannot be read
     */
    public static function of(string $disk, string $path): self
    {
        if (self::isLocal($disk)) {
            return new self(Storage::disk($disk)->path($path), false);
        }

        $source = Storage::disk($disk)->readStream($path);
        $target = self::temporaryPath(pathinfo($path, PATHINFO_EXTENSION));
        $handle = fopen($target, 'wb');

        if ($source === null || $handle === false || stream_copy_to_stream($source, $handle) === false) {
            @unlink($target);

            throw new RuntimeException("The stored file [{$path}] could not be read.");
        }

        fclose($handle);
        fclose($source);

        return new self($target, true);
    }

    /**
     * Where to write a file that is to be stored at $target once it is
     * complete, so nothing ever reads half of it.
     */
    public static function scratch(string $disk, string $target): string
    {
        return self::isLocal($disk)
            ? Storage::disk($disk)->path($target).'.'.bin2hex(random_bytes(4)).'.tmp'
            : self::temporaryPath(pathinfo($target, PATHINFO_EXTENSION));
    }

    /**
     * Store the finished scratch file at $target and remove the scratch file.
     */
    public static function publish(string $disk, string $target, string $scratch): bool
    {
        if (self::isLocal($disk)) {
            return rename($scratch, Storage::disk($disk)->path($target));
        }

        $stream = fopen($scratch, 'rb');
        $stored = $stream !== false && Storage::disk($disk)->writeStream($target, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        @unlink($scratch);

        return $stored;
    }

    private static function temporaryPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stored-');

        if ($path === false) {
            throw new RuntimeException('No temporary file could be created.');
        }

        if (preg_match('/^[A-Za-z0-9]{1,10}$/', $extension) !== 1) {
            return $path;
        }

        // Tools such as ffmpeg and the statement readers go by the extension.
        rename($path, "{$path}.{$extension}");

        return "{$path}.{$extension}";
    }
}
