<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum ExclusionCode: string
{
    case LegalOutcomeBlocked = 'legal_outcome_blocked';
    case ProductUnpublished = 'product_unpublished';
    case ProductInactive = 'product_inactive';
    case VariantUnavailable = 'variant_unavailable';
    case OutOfStock = 'out_of_stock';
    case ActivityIncompatible = 'activity_incompatible';
    case SpeciesIncompatible = 'species_incompatible';
    case SpeciesCategoryIncompatible = 'species_category_incompatible';
    case EquipmentProhibited = 'equipment_prohibited';
    case MethodProhibited = 'method_prohibited';
    case RegionRestricted = 'region_restricted';
    case ZoneRestricted = 'zone_restricted';
    case SaleRestricted = 'sale_restricted';
    case AssignmentExpired = 'assignment_expired';
    case ManualExclusion = 'manual_exclusion';
    case CompatibilityConflict = 'compatibility_conflict';
    case MissingRequiredProductData = 'missing_required_product_data';
}
