<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Exceptions\RecommendationException;

/**
 * Variant rows replace product-level positive rows for the same term.
 * A product-level exclusion always defeats a variant-level positive row.
 *
 * @phpstan-type AssignmentRow array{
 *     term_id: int,
 *     dimension: string,
 *     code: string,
 *     type: string,
 *     variant_id: int|null,
 *     source: string,
 *     weight: float
 * }
 */
final class AssignmentResolver
{
    /**
     * @param  list<AssignmentRow>  $rows
     * @return array{rows: list<AssignmentRow>, conflicts: list<string>}
     */
    public function forVariant(array $rows, ?int $variantId): array
    {
        $productExcluded = [];
        $conflicts = [];

        foreach ($rows as $row) {
            $this->assertRow($row);
            if ($row['variant_id'] === null && $row['type'] === AssignmentType::Excluded->value) {
                $productExcluded[$row['term_id']] = $row['code'];
            }
        }

        $chosen = [];
        foreach ($rows as $row) {
            if ($variantId !== null && $row['variant_id'] !== null && $row['variant_id'] !== $variantId) {
                continue;
            }
            if ($variantId === null && $row['variant_id'] !== null) {
                continue;
            }
            if (isset($productExcluded[$row['term_id']]) && $row['type'] !== AssignmentType::Excluded->value) {
                $conflicts[] = $row['dimension'].':'.$row['code'];

                continue;
            }
            $key = $row['term_id'];
            $existing = $chosen[$key] ?? null;
            if ($existing === null) {
                $chosen[$key] = $row;

                continue;
            }
            if ($row['variant_id'] !== null && $existing['variant_id'] === null && $row['type'] !== AssignmentType::Excluded->value) {
                $chosen[$key] = $row;

                continue;
            }
            if ($row['type'] === AssignmentType::Excluded->value) {
                $chosen[$key] = $row;
                if ($existing['type'] !== AssignmentType::Excluded->value) {
                    $conflicts[] = $row['dimension'].':'.$row['code'];
                }
            }
        }

        return ['rows' => array_values($chosen), 'conflicts' => array_values(array_unique($conflicts))];
    }

    /**
     * @param  AssignmentRow  $row
     */
    private function assertRow(array $row): void
    {
        if (AssignmentType::tryFrom($row['type']) === null || AssignmentSourceType::tryFrom($row['source']) === null) {
            throw RecommendationException::invalid('Assignment type or source is not allowed.');
        }
    }
}
