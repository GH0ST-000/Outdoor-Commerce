<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Enums;

enum PricePeriodStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
}
