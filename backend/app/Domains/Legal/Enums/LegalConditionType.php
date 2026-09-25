<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalConditionType: string
{
    case Species = 'species';
    case Activity = 'activity';
    case Date = 'date';
    case Age = 'age';
    case License = 'license';
    case Permit = 'permit';
    case Equipment = 'equipment';
    case Method = 'method';
    case DailyQuantity = 'daily_quantity';
    case SeasonQuantity = 'season_quantity';
    case Size = 'size';
    case Sex = 'sex';
    case LifeStage = 'life_stage';
    case Jurisdiction = 'jurisdiction';
    case Region = 'region';
    case Zone = 'zone';
    case TimeOfDay = 'time_of_day';
    case UserCategory = 'user_category';
    case Other = 'other';
}
