<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ChangeDetectionStatus: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';
    case VersionCreated = 'version_created';
}
