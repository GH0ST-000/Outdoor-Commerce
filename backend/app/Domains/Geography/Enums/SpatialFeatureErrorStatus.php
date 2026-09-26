<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialFeatureErrorStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
