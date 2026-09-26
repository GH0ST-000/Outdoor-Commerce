<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialDatasetType: string
{
    case ProtectedAreas = 'protected_areas';
    case HuntingRestrictions = 'hunting_restrictions';
    case FishingRestrictions = 'fishing_restrictions';
    case AdministrativeBoundaries = 'administrative_boundaries';
    case WildlifeManagementAreas = 'wildlife_management_areas';
    case SpecialRegulationZones = 'special_regulation_zones';
    case Other = 'other';
}
