<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialZoneType: string
{
    case ProtectedArea = 'protected_area';
    case NationalPark = 'national_park';
    case StrictNatureReserve = 'strict_nature_reserve';
    case ManagedReserve = 'managed_reserve';
    case NaturalMonument = 'natural_monument';
    case AdministrativeRegion = 'administrative_region';
    case Municipality = 'municipality';
    case HuntingRestrictedArea = 'hunting_restricted_area';
    case FishingRestrictedArea = 'fishing_restricted_area';
    case WildlifeManagementArea = 'wildlife_management_area';
    case SpecialRegulationZone = 'special_regulation_zone';
    case Other = 'other';
}
