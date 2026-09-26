<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialAssignmentType: string
{
    case AppliesWithin = 'applies_within';
    case DoesNotApplyWithin = 'does_not_apply_within';
    case ExceptionWithin = 'exception_within';
    case ProhibitedWithin = 'prohibited_within';
    case ConditionalWithin = 'conditional_within';
    case AppliesOutside = 'applies_outside';
}
