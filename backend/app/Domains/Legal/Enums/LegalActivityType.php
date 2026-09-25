<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalActivityType: string
{
    case Hunting = 'hunting';
    case Fishing = 'fishing';
    case Possession = 'possession';
    case Transport = 'transport';
    case Sale = 'sale';
    case EquipmentUse = 'equipment_use';
    case Access = 'access';
    case Other = 'other';
}
