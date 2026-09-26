<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum ReasonCode: string
{
    case ActivityMatch = 'activity_match';
    case MethodMatch = 'method_match';
    case SpeciesCategoryMatch = 'species_category_match';
    case RequiredEquipmentMatch = 'required_equipment_match';
    case SeasonPhaseMatch = 'season_phase_match';
    case InStock = 'in_stock';
    case ContextApplicable = 'context_applicable';
    case SpeciesRelatedUnlocated = 'species_related_unlocated';
    case GeneralCatalogSuggestion = 'general_catalog_suggestion';
    case ConditionalRestriction = 'conditional_restriction';
    case Promoted = 'promoted';
    case RegionMatch = 'region_match';
    case ZoneTypeMatch = 'zone_type_match';
}
