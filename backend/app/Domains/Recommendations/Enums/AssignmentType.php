<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum AssignmentType: string
{
    case RequiredMatch = 'required_match';
    case PreferredMatch = 'preferred_match';
    case Supported = 'supported';
    case Neutral = 'neutral';
    case Excluded = 'excluded';

    public function isPositive(): bool
    {
        return $this === self::RequiredMatch
            || $this === self::PreferredMatch
            || $this === self::Supported;
    }
}
