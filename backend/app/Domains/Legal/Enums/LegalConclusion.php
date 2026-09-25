<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalConclusion: string
{
    case Allowed = 'allowed';
    case Prohibited = 'prohibited';
    case Conditional = 'conditional';
    case Unknown = 'unknown';
    case Conflict = 'conflict';
}
