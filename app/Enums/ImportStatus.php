<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Review = 'review';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
