<?php

declare(strict_types=1);

namespace App\Domains\Legal\Queries;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\LegalEvaluationFactsData;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Services\LegalPresenter;
use App\Domains\Legal\Services\LegalPublicCache;
use App\Domains\Legal\Services\LegalRuleEvaluator;

final class GetSpeciesLegalOverviewQuery
{
    public function __construct(
        private readonly LegalRuleEvaluator $evaluator,
        private readonly LegalPresenter $presenter,
        private readonly LegalPublicCache $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forSpecies(Species $species, string $jurisdiction = 'GE'): array
    {
        return $this->cache->remember('species.legal_overview', [
            'species' => $species->public_id,
            'jurisdiction' => $jurisdiction,
        ], function () use ($species, $jurisdiction): array {
            $hasPublished = LegalRule::query()
                ->where('status', LegalRuleStatus::Published)
                ->where(function ($query) use ($species): void {
                    $query->where('species_id', $species->id)->orWhereNull('species_id');
                })
                ->exists();

            if (! $hasPublished) {
                return $this->presenter->unknownOverview();
            }

            $activity = $this->mapActivity($species);
            $result = $this->evaluator->evaluate(new LegalEvaluationFactsData(
                activityType: $activity,
                jurisdictionCode: $jurisdiction,
                occurredAt: now(),
                speciesId: $species->id,
            ));

            return [
                'available' => $result['outcome'] !== LegalConclusion::Unknown->value,
                'outcome' => $result['outcome'],
                'message_key' => $result['outcome'] === LegalConclusion::Unknown->value
                    ? 'species.legal_information_not_yet_available'
                    : 'legal.informational_not_advice',
                'summary' => $result['summary'],
                'rules' => $result['matched_rules'],
                'limits' => $result['applicable_limits'],
                'citations' => $result['citations'],
                'conflicts' => $result['conflicts'],
                'last_verified_at' => $result['last_verified_at'],
                'disclaimer' => $result['disclaimer'],
            ];
        });
    }

    private function mapActivity(Species $species): LegalActivityType
    {
        $value = $species->activity_type->value;

        return match (true) {
            str_contains($value, 'fishing') => LegalActivityType::Fishing,
            str_contains($value, 'hunting') => LegalActivityType::Hunting,
            default => LegalActivityType::Hunting,
        };
    }
}
