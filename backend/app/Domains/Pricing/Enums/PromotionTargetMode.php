<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Enums;

enum PromotionTargetMode: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}
