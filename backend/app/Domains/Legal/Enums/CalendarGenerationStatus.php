<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum CalendarGenerationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';
}
