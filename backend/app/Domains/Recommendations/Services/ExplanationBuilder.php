<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Enums\ReasonCode;

/**
 * Reasons are stable codes. Localized sentences are looked up from those codes.
 *
 * @param  list<array{dimension: string, points: float}>  $contributions
 */
final class ExplanationBuilder
{
    /**
     * @param  list<array{dimension: string, points: float}>  $contributions
     * @param  list<string>  $warningCodes
     * @return array{primary: string, supporting: list<string>, warnings: list<string>}
     */
    public function build(array $contributions, bool $inStock, bool $promoted, string $framing, array $warningCodes): array
    {
        $ranked = $contributions;
        usort($ranked, static fn (array $left, array $right): int => $right['points'] <=> $left['points']);
        $supporting = [];
        foreach ($ranked as $row) {
            if ($row['points'] <= 0) {
                continue;
            }
            $code = $this->codeFor($row['dimension']);
            if ($code !== null) {
                $supporting[] = $code;
            }
        }
        if ($inStock) {
            $supporting[] = ReasonCode::InStock->value;
        }
        if ($framing === 'species_related') {
            array_unshift($supporting, ReasonCode::SpeciesRelatedUnlocated->value);
        }
        if ($framing === 'general_discovery') {
            array_unshift($supporting, ReasonCode::GeneralCatalogSuggestion->value);
        }
        if ($promoted) {
            $supporting[] = ReasonCode::Promoted->value;
        }
        $supporting = array_values(array_unique($supporting));
        $primary = $supporting[0] ?? ReasonCode::ContextApplicable->value;

        return [
            'primary' => $primary,
            'supporting' => array_values(array_filter($supporting, static fn (string $code): bool => $code !== $primary)),
            'warnings' => array_values(array_unique($warningCodes)),
        ];
    }

    private function codeFor(string $dimension): ?string
    {
        return match ($dimension) {
            'activity_match' => ReasonCode::ActivityMatch->value,
            'method_match' => ReasonCode::MethodMatch->value,
            'species_category_match' => ReasonCode::SpeciesCategoryMatch->value,
            'species_exact_match' => ReasonCode::ContextApplicable->value,
            'required_equipment_match' => ReasonCode::RequiredEquipmentMatch->value,
            'season_phase_match' => ReasonCode::SeasonPhaseMatch->value,
            'region_match' => ReasonCode::RegionMatch->value,
            'zone_type_match' => ReasonCode::ZoneTypeMatch->value,
            'availability' => ReasonCode::InStock->value,
            default => null,
        };
    }
}
