<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum ContextDimension: string
{
    case Activity = 'activity';
    case Species = 'species';
    case SpeciesCategory = 'species_category';
    case Equipment = 'equipment';
    case Method = 'method';
    case Permit = 'permit';
    case License = 'license';
    case SeasonPhase = 'season_phase';
    case Region = 'region';
    case ZoneType = 'zone_type';
    case Environment = 'environment';
    case TripDuration = 'trip_duration';
    case Measurement = 'measurement';
    case UseCase = 'use_case';
}
