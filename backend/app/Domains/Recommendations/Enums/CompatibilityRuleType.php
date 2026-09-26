<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum CompatibilityRuleType: string
{
    case Include = 'include';
    case Exclude = 'exclude';
    case Require = 'require';
    case Prefer = 'prefer';
    case Penalize = 'penalize';
}
