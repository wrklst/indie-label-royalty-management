<?php

namespace App\Enums;

enum ReleaseFileKind: string
{
    case Cover = 'cover';
    case Audio = 'audio';
    case Artwork = 'artwork';
    case Document = 'document';
    case Video = 'video';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Pick the kind a freshly uploaded file most likely is.
     */
    public static function guess(?string $mimeType, string $extension): self
    {
        $extension = strtolower($extension);

        return match (true) {
            str_starts_with((string) $mimeType, 'audio/') || in_array($extension, ['wav', 'flac', 'aif', 'aiff', 'mp3', 'm4a', 'ogg'], true) => self::Audio,
            str_starts_with((string) $mimeType, 'image/') => self::Artwork,
            str_starts_with((string) $mimeType, 'video/') => self::Video,
            in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'md', 'csv', 'xlsx'], true) => self::Document,
            default => self::Other,
        };
    }
}
