<?php

namespace App\Enums;

enum ReleaseType: string
{
    case Album = 'album';
    case Ep = 'ep';
    case Single = 'single';
    case Compilation = 'compilation';

    public function label(): string
    {
        return match ($this) {
            self::Album => 'Album',
            self::Ep => 'EP',
            self::Single => 'Single',
            self::Compilation => 'Compilation',
        };
    }
}
