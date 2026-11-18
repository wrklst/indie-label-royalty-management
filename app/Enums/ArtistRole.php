<?php

namespace App\Enums;

enum ArtistRole: string
{
    case Primary = 'primary';
    case Featuring = 'featuring';
    case Remixer = 'remixer';
    case Producer = 'producer';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
