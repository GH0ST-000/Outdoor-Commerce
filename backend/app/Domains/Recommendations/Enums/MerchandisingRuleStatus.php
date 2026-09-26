<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum MerchandisingRuleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Archived = 'archived';
}
