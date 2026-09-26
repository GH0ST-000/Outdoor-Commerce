<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum ScoreDimension: string
{
    case ActivityMatch = 'activity_match';
    case SpeciesExactMatch = 'species_exact_match';
    case SpeciesCategoryMatch = 'species_category_match';
    case RequiredEquipmentMatch = 'required_equipment_match';
    case MethodMatch = 'method_match';
    case SeasonPhaseMatch = 'season_phase_match';
    case RegionMatch = 'region_match';
    case ZoneTypeMatch = 'zone_type_match';
    case GeneralRelevance = 'general_relevance';
    case SpecificationCompleteness = 'specification_completeness';
    case Availability = 'availability';
    case Popularity = 'popularity';
    case Recency = 'recency';
}
