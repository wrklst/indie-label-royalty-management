<?php

namespace App\Enums;

enum ArtistRole: string
{
    case Primary = 'primary';
    case Featuring = 'featuring';
    case Remixer = 'remixer';
    case Producer = 'producer';
    case With = 'with';
    case Versus = 'versus';
    case Meets = 'meets';
    case Presents = 'presents';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
