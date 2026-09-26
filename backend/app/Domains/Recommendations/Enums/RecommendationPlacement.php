<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum RecommendationPlacement: string
{
    case OutdoorContextResult = 'outdoor_context_result';
    case SpeciesDetail = 'species_detail';
    case SeasonExplorer = 'season_explorer';
    case MapLocationResult = 'map_location_result';
    case ProductDetailRelated = 'product_detail_related';
    case CartContextual = 'cart_contextual';

    public function showsUnlocatedSpeciesGear(): bool
    {
        return $this === self::SpeciesDetail || $this === self::SeasonExplorer;
    }
}
