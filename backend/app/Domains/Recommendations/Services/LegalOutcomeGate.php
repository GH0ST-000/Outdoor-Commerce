<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Recommendations\Enums\RecommendationGate;

/**
 * Maps an already-computed legal conclusion onto a commerce gate.
 * This class does not evaluate law and cannot change a legal outcome.
 */
final class LegalOutcomeGate
{
    public function fromConclusion(
        LegalConclusion $conclusion,
        bool $boundaryUncertain,
        bool $spatiallyVerified,
    ): RecommendationGate {
        if ($conclusion === LegalConclusion::Conflict || $conclusion === LegalConclusion::Prohibited) {
            return RecommendationGate::Blocked;
        }

        if ($conclusion === LegalConclusion::Unknown || $boundaryUncertain) {
            return RecommendationGate::Unknown;
        }

        if ($conclusion === LegalConclusion::Conditional) {
            return RecommendationGate::InformationOnly;
        }

        if (! $spatiallyVerified) {
            return RecommendationGate::InformationOnly;
        }

        return RecommendationGate::Allowed;
    }
}
