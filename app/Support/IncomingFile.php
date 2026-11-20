<?php

namespace App\Support;

use finfo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A file a user has just handed over: either posted to this server
 * ("file"), or already sent to the bucket by the browser and reported
 * by its key ("upload", with "upload_name").
 */
final class IncomingFile
{
    /** The rules every action that takes a file adds to its own for "file". */
    public const array RULES = [
        'upload' => ['nullable', 'string', 'max:255'],
        'upload_name' => ['nullable', 'string', 'max:255'],
    ];

    private ?WorkingCopy $copy = null;

    private function __construct(
        public readonly string $name,
        public readonly ?string $mimeType,
        public readonly int $size,
        private readonly ?UploadedFile $upload,
        private readonly ?string $key = null,
    ) {}

    /**
     * The file of a validated request. A posted file has passed the
     * request's own rules; one in the bucket is held to the same limits
     * here and removed when it breaks them.
     *
     * @param  list<string>  $mimeTypes  Empty accepts every type.
     * @param  list<string>  $extensions  Empty accepts every extension.
     * @param  array{max?: string, type?: string}  $messages
     *
     * @throws ValidationException
     */
    public static function from(Request $request, int $labelId, int $maxKilobytes, array $mimeTypes = [], array $extensions = [], array $messages = []): self
    {
        $upload = $request->file('file');

        if ($upload instanceof UploadedFile) {
            return new self(Str::limit(basename($upload->getClientOriginalName()), 250, ''), $upload->getMimeType(), (int) $upload->getSize(), $upload);
        }

        $key = (string) $request->input('upload');
        $disk = Storage::disk(WorkingCopy::disk());

        if (! DirectUploads::owns($key, $labelId) || ! $disk->exists($key)) {
            throw ValidationException::withMessages(['file' => __('The upload did not arrive. Try again.')]);
        }

        // The type is read off the file itself, never taken from the browser.
        $stream = $disk->readStream($key);
        $head = is_resource($stream) ? (string) stream_get_contents($stream, 262144) : '';

        if (is_resource($stream)) {
            fclose($stream);
        }

        $file = new self(
            Str::limit(basename((string) $request->input('upload_name', $key)), 250, ''),
            (new finfo(FILEINFO_MIME_TYPE))->buffer($head) ?: null,
            $disk->size($key),
            null,
            $key,
        );

        $problem = match (true) {
            $file->size > $maxKilobytes * 1024 => $messages['max'] ?? __('The file is larger than :size MB.', ['size' => round($maxKilobytes / 1024)]),
            $mimeTypes !== [] && ! in_array($file->mimeType, $mimeTypes, true),
            $extensions !== [] && ! in_array($file->extension(), $extensions, true) => $messages['type'] ?? __('Files of this type cannot be stored here.'),
            default => null,
        };

        if ($problem !== null) {
            $file->discard();

            throw ValidationException::withMessages(['file' => $problem]);
        }

        return $file;
    }

    /**
     * The extension the user's file name ends in, when it is fit to become part of a stored path.
     */
    public function extension(): string
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : (string) $this->upload?->guessExtension();
    }

    /**
     * Put the file in its place. Returns the stored path, or false.
     */
    public function storeAs(string $directory, string $name, string $disk): string|false
    {
        if ($this->upload !== null) {
            return $this->upload->storeAs($directory, $name, $disk);
        }

        return Storage::disk($disk)->move((string) $this->key, "{$directory}/{$name}") ? "{$directory}/{$name}" : false;
    }

    /**
     * The file as a path on this machine, for code that has to read it now.
     */
    public function path(): string
    {
        if ($this->upload !== null) {
            return $this->upload->getRealPath();
        }

        return ($this->copy ??= WorkingCopy::of(WorkingCopy::disk(), (string) $this->key))->path;
    }

    /**
     * ponytail: none for a file sent straight to the bucket, since hashing it means downloading it;
     * nothing depends on it. Hash in a queued job if masters are ever to be verified.
     */
    public function checksum(): ?string
    {
        return $this->upload !== null ? (hash_file('sha256', $this->upload->getRealPath()) ?: null) : null;
    }

    /**
     * Remove a file that is refused, so it does not stay in the bucket.
     */
    public function discard(): void
    {
        if ($this->key !== null) {
            Storage::disk(WorkingCopy::disk())->delete($this->key);
        }
    }
}
