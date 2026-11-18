<?php

namespace App\Enums;

/**
 * How far the MP3 an audio file is played from has come.
 */
enum AudioPreviewStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
