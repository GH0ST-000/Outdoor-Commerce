<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Enums;

enum AssignmentSourceType: string
{
    case ManualVerified = 'manual_verified';
    case ManufacturerSpecification = 'manufacturer_specification';
    case CatalogAttribute = 'catalog_attribute';
    case LegalRule = 'legal_rule';
    case SpeciesKnowledge = 'species_knowledge';
    case SystemDerived = 'system_derived';

    public function isVerified(): bool
    {
        return $this === self::ManualVerified
            || $this === self::LegalRule
            || $this === self::SpeciesKnowledge;
    }
}
