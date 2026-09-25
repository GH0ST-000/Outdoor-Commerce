<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalConflictSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
