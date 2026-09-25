<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum MigrationPattern: string
{
    case Resident = 'resident';
    case SeasonalMigrant = 'seasonal_migrant';
    case PartialMigrant = 'partial_migrant';
    case Nomadic = 'nomadic';
}
