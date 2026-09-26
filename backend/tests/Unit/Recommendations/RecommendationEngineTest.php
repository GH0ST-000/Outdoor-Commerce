<?php

declare(strict_types=1);

use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Recommendations\Enums\CompatibilityOperator;
use App\Domains\Recommendations\Enums\ExclusionCode;
use App\Domains\Recommendations\Enums\RecommendationConfidence;
use App\Domains\Recommendations\Enums\RecommendationGate;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Services\AssignmentResolver;
use App\Domains\Recommendations\Services\CompatibilityRuleEvaluator;
use App\Domains\Recommendations\Services\ConfidenceCalculator;
use App\Domains\Recommendations\Services\ExplanationBuilder;
use App\Domains\Recommendations\Services\HardExclusionEngine;
use App\Domains\Recommendations\Services\LegalOutcomeGate;
use App\Domains\Recommendations\Services\MerchandisingAdjuster;
use App\Domains\Recommendations\Services\RecommendationScorer;
use App\Domains\Recommendations\Services\TieBreaker;
use App\Domains\Recommendations\Support\RecommendationCacheKey;

it('maps legal conclusions to recommendation gates without treating unverified allowance as allowed', function (): void {
    $gate = new LegalOutcomeGate;

    expect($gate->fromConclusion(LegalConclusion::Allowed, false, true))->toBe(RecommendationGate::Allowed)
        ->and($gate->fromConclusion(LegalConclusion::Allowed, false, false))->toBe(RecommendationGate::InformationOnly)
        ->and($gate->fromConclusion(LegalConclusion::Allowed, true, true))->toBe(RecommendationGate::Unknown)
        ->and($gate->fromConclusion(LegalConclusion::Conditional, false, true))->toBe(RecommendationGate::InformationOnly)
        ->and($gate->fromConclusion(LegalConclusion::Prohibited, false, true))->toBe(RecommendationGate::Blocked)
        ->and($gate->fromConclusion(LegalConclusion::Conflict, false, true))->toBe(RecommendationGate::Blocked)
        ->and($gate->fromConclusion(LegalConclusion::Unknown, false, true))->toBe(RecommendationGate::Unknown);
});

it('lets a variant exclusion replace a product match and keeps product exclusions', function (): void {
    $resolver = new AssignmentResolver;
    $rows = [
        row(1, 'species', 'roe', 'preferred_match', null),
        row(1, 'species', 'roe', 'excluded', 9),
        row(2, 'activity', 'hunting', 'excluded', null),
        row(2, 'activity', 'hunting', 'preferred_match', 9),
    ];

    $variant = $resolver->forVariant($rows, 9);

    expect(collect($variant['rows'])->firstWhere('term_id', 1)['type'])->toBe('excluded')
        ->and(collect($variant['rows'])->firstWhere('term_id', 2)['type'])->toBe('excluded')
        ->and($variant['conflicts'])->toContain('activity:hunting');
});

it('rejects operators outside the whitelist', function (): void {
    $evaluator = new CompatibilityRuleEvaluator;

    expect(fn () => $evaluator->evaluate([
        ['type' => 'exclude', 'dimension' => 'activity', 'operator' => 'union select', 'value' => 'hunting', 'integer_value' => null, 'decimal_value' => null, 'boolean_value' => null, 'weight' => 1],
    ], ['activity' => 'hunting']))->toThrow(RecommendationException::class);
});

it('evaluates typed include and exclude operators', function (): void {
    $evaluator = new CompatibilityRuleEvaluator;
    $result = $evaluator->evaluate([
        ['type' => 'exclude', 'dimension' => 'method', 'operator' => 'in', 'value' => 'firearm,net', 'integer_value' => null, 'decimal_value' => null, 'boolean_value' => null, 'weight' => 0],
        ['type' => 'prefer', 'dimension' => 'activity', 'operator' => 'equals', 'value' => 'hunting', 'integer_value' => null, 'decimal_value' => null, 'boolean_value' => null, 'weight' => 0],
        ['type' => 'require', 'dimension' => 'species', 'operator' => 'equals', 'value' => 'roe-deer', 'integer_value' => null, 'decimal_value' => null, 'boolean_value' => null, 'weight' => 0],
    ], ['method' => ['archery'], 'activity' => 'hunting', 'species' => 'boar']);

    expect($result['exclusions'])->toContain('species_incompatible')
        ->and($result['exclusions'])->not->toContain('method_prohibited')
        ->and($result['prefer'])->toContain('activity')
        ->and($evaluator->matches(CompatibilityOperator::Between, 4, ['value' => null, 'integer_value' => 2, 'decimal_value' => 6.0, 'boolean_value' => null, 'weight' => 0, 'type' => 'include', 'dimension' => 'measurement', 'operator' => 'between']))->toBeTrue();
});

it('excludes prohibited equipment before scoring', function (): void {
    $engine = new HardExclusionEngine;
    $codes = $engine->exclude(
        [row(3, 'equipment', 'ammunition', 'preferred_match', null)],
        [],
        [
            'activity' => 'hunting',
            'species' => null,
            'species_category' => null,
            'methods' => [],
            'prohibited_equipment' => ['ammunition'],
            'prohibited_methods' => [],
            'region' => null,
            'zone_types' => [],
            'equipment_codes' => [],
        ],
        ['published' => true, 'active' => true, 'priced' => true, 'in_stock' => true, 'deleted' => false],
        [],
        true,
        false,
    );

    expect($codes)->toContain(ExclusionCode::EquipmentProhibited->value);
});

it('normalizes scores and caps an activity-only match', function (): void {
    $scorer = new RecommendationScorer;
    $weights = config('recommendations.default_weights');
    $full = $scorer->score([
        'activity_match' => 1,
        'species_exact_match' => 1,
        'specification_completeness' => 1,
        'availability' => 1,
    ], $weights, ['activity_match', 'species_exact_match', 'specification_completeness', 'availability'], 60, 0, false);
    $activity = $scorer->score([
        'activity_match' => 1,
        'specification_completeness' => 1,
        'availability' => 1,
    ], $weights, ['activity_match', 'specification_completeness', 'availability'], 60, 0, true);

    expect($full['score'])->toBeGreaterThan($activity['score'])
        ->and($activity['score'])->toBeLessThanOrEqual(60)
        ->and($full['score'])->toBeLessThanOrEqual(100);
});

it('rejects weights that do not sum to 100', function (): void {
    $weights = config('recommendations.default_weights');
    $weights['activity_match'] = 19;

    expect(fn () => (new RecommendationScorer)->assertWeights($weights))
        ->toThrow(RecommendationException::class);
});

it('caps merchandising boosts and does not treat pin as eligibility', function (): void {
    $adjuster = new MerchandisingAdjuster;
    $boost = $adjuster->apply(90, 'boost', 50, 1, 8, true);
    $pin = $adjuster->apply(10, 'pin', 1, 2, 8, false);

    expect($boost['final'])->toBe(98)
        ->and($boost['adjustment'])->toBe(8)
        ->and($boost['promoted'])->toBeTrue()
        ->and($pin['pinned'])->toBeTrue()
        ->and($pin['final'])->toBe(10)
        ->and($adjuster->apply(40, 'exclude', 100, 1, 8, false)['excluded'])->toBeTrue();
});

it('breaks ties deterministically', function (): void {
    $sorted = (new TieBreaker)->sort([
        rank('b', 80, false),
        rank('a', 80, false),
        rank('c', 70, true),
    ]);

    expect(array_column($sorted, 'slug'))->toBe(['c', 'a', 'b']);
});

it('separates confidence from score', function (): void {
    $calculator = new ConfidenceCalculator;
    $high = $calculator->calculate([
        ['dimension' => 'species', 'code' => 'roe', 'type' => 'preferred_match', 'source' => 'manual_verified'],
        ['dimension' => 'activity', 'code' => 'hunting', 'type' => 'supported', 'source' => 'species_knowledge'],
    ], 'high', true, false);

    expect($high)->toBe(RecommendationConfidence::High)
        ->and($calculator->calculate([
            ['dimension' => 'activity', 'code' => 'hunting', 'type' => 'supported', 'source' => 'manual_verified'],
        ], 'partial', true, true))->toBe(RecommendationConfidence::Low)
        ->and($calculator->calculate([], 'high', true, false))->toBe(RecommendationConfidence::Insufficient);
});

it('builds explanations from reason codes', function (): void {
    $explanation = (new ExplanationBuilder)->build([
        ['dimension' => 'species_exact_match', 'points' => 22],
        ['dimension' => 'activity_match', 'points' => 18],
    ], true, false, 'contextual', []);

    expect($explanation['primary'])->toBe('context_applicable')
        ->and($explanation['supporting'])->toContain('activity_match')
        ->and($explanation['supporting'])->toContain('in_stock');
});

it('drops coordinates from recommendation cache keys', function (): void {
    $keys = new RecommendationCacheKey;
    $left = $keys->make(['placement' => 'map_location_result', 'zone_public_ids' => ['z1'], 'lat' => 41.7, 'lng' => 44.8]);
    $right = $keys->make(['placement' => 'map_location_result', 'zone_public_ids' => ['z1'], 'latitude' => 0, 'longitude' => 1]);

    expect($left)->toBe($right)
        ->and($keys->strip(['coordinate' => ['lat' => 1], 'activity' => 'hunting']))->not->toHaveKey('coordinate');
});

function row(int $term, string $dimension, string $code, string $type, ?int $variant): array
{
    return [
        'term_id' => $term,
        'dimension' => $dimension,
        'code' => $code,
        'type' => $type,
        'variant_id' => $variant,
        'source' => 'manual_verified',
        'weight' => 1,
    ];
}

function rank(string $slug, int $score, bool $pinned): array
{
    return [
        'pinned' => $pinned,
        'pin_priority' => $pinned ? 1 : 100000,
        'final_score' => $score,
        'confidence_rank' => 2,
        'in_stock' => true,
        'merchandising_priority' => 100,
        'published_at' => 10,
        'slug' => $slug,
    ];
}
