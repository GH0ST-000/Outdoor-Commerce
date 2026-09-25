<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum HabitatImportance: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Occasional = 'occasional';
}
