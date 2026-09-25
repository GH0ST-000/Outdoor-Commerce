<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum CalendarAvailabilityState: string
{
    case Open = 'open';
    case PartiallyOpen = 'partially_open';
    case Closed = 'closed';
    case Conditional = 'conditional';
    case Unknown = 'unknown';
    case Conflict = 'conflict';
}
