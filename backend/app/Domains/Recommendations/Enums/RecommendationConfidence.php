<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum RecommendationConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Insufficient = 'insufficient';

    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Insufficient => 0,
        };
    }
}
