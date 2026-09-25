<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ExtractionStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case ManualOnly = 'manual_only';
}
