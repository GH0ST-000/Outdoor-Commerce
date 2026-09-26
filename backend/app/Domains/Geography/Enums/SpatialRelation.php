<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialRelation: string
{
    case Inside = 'inside';
    case Outside = 'outside';
    case OnBoundary = 'on_boundary';
}
