<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum RecommendationGate: string
{
    case Allowed = 'recommendations_allowed';
    case InformationOnly = 'recommendations_information_only';
    case Blocked = 'recommendations_blocked';
    case Unknown = 'recommendations_unknown';
}
