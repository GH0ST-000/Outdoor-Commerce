<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalRuleType: string
{
    case Permission = 'permission';
    case Prohibition = 'prohibition';
    case SeasonalRestriction = 'seasonal_restriction';
    case BagLimit = 'bag_limit';
    case CatchLimit = 'catch_limit';
    case SizeLimit = 'size_limit';
    case PermitRequirement = 'permit_requirement';
    case LicenseRequirement = 'license_requirement';
    case MethodRestriction = 'method_restriction';
    case EquipmentRestriction = 'equipment_restriction';
    case LocationRestriction = 'location_restriction';
    case ReportingRequirement = 'reporting_requirement';
    case EligibilityRequirement = 'eligibility_requirement';
    case Exception = 'exception';
    case Other = 'other';
}
