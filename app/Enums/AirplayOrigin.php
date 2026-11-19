<?php

namespace App\Enums;

/**
 * How the label learned of a play.
 */
enum AirplayOrigin: string
{
    case Statement = 'statement';
    case Search = 'search';
    case Manual = 'manual';
}
