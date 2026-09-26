<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\RecommendationConfidence;

/**
 * Confidence is not a legal conclusion and cannot override a hard exclusion.
 *
 * @phpstan-type AssignmentRow array{dimension: string, code: string, type: string, source: string}
 */
final class ConfidenceCalculator
{
    /**
     * @param  list<AssignmentRow>  $matched
     */
    public function calculate(array $matched, string $completeness, bool $specsComplete, bool $activityOnly): RecommendationConfidence
    {
        if ($matched === []) {
            return RecommendationConfidence::Insufficient;
        }

        $verified = 0;
        $exactSpecies = false;
        $category = false;
        foreach ($matched as $row) {
            $source = AssignmentSourceType::tryFrom($row['source']);
            if ($source?->isVerified() === true) {
                $verified++;
            }
            if ($row['dimension'] === 'species') {
                $exactSpecies = true;
            }
            if ($row['dimension'] === 'species_category' || $row['dimension'] === 'equipment') {
                $category = true;
            }
        }

        if ($verified >= 2 && $completeness === 'high' && $specsComplete && ($exactSpecies || $category)) {
            return RecommendationConfidence::High;
        }
        if (($verified >= 1 || $category || $exactSpecies) && $completeness !== 'insufficient' && ! $activityOnly) {
            return RecommendationConfidence::Medium;
        }
        if ($activityOnly || $completeness === 'partial') {
            return RecommendationConfidence::Low;
        }

        return RecommendationConfidence::Insufficient;
    }
}
