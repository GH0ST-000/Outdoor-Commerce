<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum ConservationAssessmentScope: string
{
    case Global = 'global';
    case National = 'national';
    case Regional = 'regional';
}
