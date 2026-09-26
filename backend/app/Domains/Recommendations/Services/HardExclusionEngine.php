<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Enums\ExclusionCode;

/**
 * Hard exclusions run before any score is calculated.
 *
 * @phpstan-type AssignmentRow array{term_id: int, dimension: string, code: string, type: string, variant_id: int|null, source: string, weight: float}
 */
final class HardExclusionEngine
{
    /**
     * @param  list<AssignmentRow>  $rows
     * @param  list<string>  $conflicts
     * @param  array{
     *     activity: string,
     *     species: string|null,
     *     species_category: string|null,
     *     methods: list<string>,
     *     prohibited_equipment: list<string>,
     *     prohibited_methods: list<string>,
     *     region: string|null,
     *     zone_types: list<string>,
     *     equipment_codes: list<string>
     * }  $context
     * @param  array{published: bool, active: bool, priced: bool, in_stock: bool, deleted: bool}  $commerce
     * @param  list<string>  $ruleExclusions
     * @return list<string>
     */
    public function exclude(array $rows, array $conflicts, array $context, array $commerce, array $ruleExclusions, bool $hideOutOfStock, bool $manualExclude): array
    {
        $codes = [];
        if ($commerce['deleted'] || ! $commerce['published']) {
            $codes[] = ExclusionCode::ProductUnpublished->value;
        }
        if (! $commerce['active']) {
            $codes[] = ExclusionCode::ProductInactive->value;
        }
        if (! $commerce['priced']) {
            $codes[] = ExclusionCode::MissingRequiredProductData->value;
        }
        if (! $commerce['in_stock'] && $hideOutOfStock) {
            $codes[] = ExclusionCode::OutOfStock->value;
        }
        if ($conflicts !== []) {
            $codes[] = ExclusionCode::CompatibilityConflict->value;
        }
        if ($manualExclude) {
            $codes[] = ExclusionCode::ManualExclusion->value;
        }

        $equipmentCodes = [];
        foreach ($rows as $row) {
            if ($row['dimension'] === 'equipment') {
                $equipmentCodes[] = $row['code'];
            }
            if ($row['type'] !== AssignmentType::Excluded->value) {
                continue;
            }
            $codes[] = match ($row['dimension']) {
                'activity' => $row['code'] === $context['activity'] ? ExclusionCode::ActivityIncompatible->value : null,
                'species' => $context['species'] !== null && $row['code'] === $context['species'] ? ExclusionCode::SpeciesIncompatible->value : null,
                'species_category' => $context['species_category'] !== null && $row['code'] === $context['species_category'] ? ExclusionCode::SpeciesCategoryIncompatible->value : null,
                'equipment' => ExclusionCode::EquipmentProhibited->value,
                'method' => $context['methods'] === [] || in_array($row['code'], $context['methods'], true) ? ExclusionCode::MethodProhibited->value : null,
                'region' => $context['region'] !== null && $row['code'] === $context['region'] ? ExclusionCode::RegionRestricted->value : null,
                'zone_type' => array_intersect($context['zone_types'], [$row['code']]) !== [] ? ExclusionCode::ZoneRestricted->value : null,
                default => null,
            };
        }

        if (array_intersect($equipmentCodes, $context['prohibited_equipment']) !== []) {
            $codes[] = ExclusionCode::EquipmentProhibited->value;
        }
        if (array_intersect($equipmentCodes, $context['prohibited_methods']) !== [] || array_intersect(array_column(array_filter($rows, static fn (array $row): bool => $row['dimension'] === 'method'), 'code'), $context['prohibited_methods']) !== []) {
            $codes[] = ExclusionCode::MethodProhibited->value;
        }

        $activityRows = array_values(array_filter($rows, static fn (array $row): bool => $row['dimension'] === 'activity'));
        $positiveActivities = array_values(array_filter($activityRows, static fn (array $row): bool => AssignmentType::from($row['type'])->isPositive()));
        if ($context['activity'] !== '' && $positiveActivities !== []) {
            $matches = array_filter($positiveActivities, static fn (array $row): bool => $row['code'] === $context['activity']);
            $requiredElsewhere = array_filter($positiveActivities, static fn (array $row): bool => $row['type'] === AssignmentType::RequiredMatch->value && $row['code'] !== $context['activity']);
            if ($matches === [] && $requiredElsewhere !== []) {
                $codes[] = ExclusionCode::ActivityIncompatible->value;
            }
        }

        if ($context['species'] !== null) {
            $speciesRows = array_values(array_filter($rows, static fn (array $row): bool => $row['dimension'] === 'species' && AssignmentType::from($row['type'])->isPositive()));
            $requiredOther = array_filter($speciesRows, static fn (array $row): bool => $row['type'] === AssignmentType::RequiredMatch->value && $row['code'] !== $context['species']);
            $matches = array_filter($speciesRows, static fn (array $row): bool => $row['code'] === $context['species']);
            if ($requiredOther !== [] && $matches === []) {
                $codes[] = ExclusionCode::SpeciesIncompatible->value;
            }
        }

        return array_values(array_unique(array_filter([...$codes, ...$ruleExclusions], static fn (?string $code): bool => is_string($code) && $code !== '')));
    }
}
