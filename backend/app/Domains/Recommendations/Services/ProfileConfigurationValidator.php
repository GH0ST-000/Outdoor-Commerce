<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Exceptions\RecommendationException;

final class ProfileConfigurationValidator
{
    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, int>  $weights
     * @return array<string, mixed>
     */
    public function validate(array $configuration, array $weights): array
    {
        $allowed = [
            'minimum_score',
            'maximum_results',
            'candidate_limit',
            'merchandising_max_points',
            'activity_only_cap',
            'stock_behavior',
            'backorder_behavior',
            'tie_break',
            'weights',
        ];
        $unknown = array_diff(array_keys($configuration), $allowed);
        if ($unknown !== []) {
            throw RecommendationException::invalid('Profile configuration contains unknown keys.', [
                'unknown' => array_values($unknown),
            ]);
        }
        $this->assertRange($configuration, 'minimum_score', 0, 100);
        $this->assertRange($configuration, 'maximum_results', 1, 24);
        $this->assertRange($configuration, 'candidate_limit', 1, (int) config('recommendations.candidate_limit_max', 200));
        $this->assertRange($configuration, 'merchandising_max_points', 0, (int) config('recommendations.merchandising_max_points', 12));
        $this->assertRange($configuration, 'activity_only_cap', 0, 100);
        if (($configuration['stock_behavior'] ?? '') !== 'hide_out_of_stock' && ($configuration['stock_behavior'] ?? '') !== 'show_unavailable') {
            throw RecommendationException::invalid('Stock behavior is not allowed.');
        }
        if (($configuration['backorder_behavior'] ?? 'unsupported') !== 'unsupported') {
            throw RecommendationException::invalid('Backorders are not supported.');
        }
        $tie = $configuration['tie_break'] ?? [];
        if ($tie !== config('recommendations.tie_break')) {
            throw RecommendationException::invalid('Tie-break order is fixed.');
        }
        /** @var array<string, int> $configWeights */
        $configWeights = $configuration['weights'] ?? [];
        if ($configWeights !== $weights) {
            throw RecommendationException::invalid('Configuration weights must match the weight rows.');
        }
        app(RecommendationScorer::class)->assertWeights($weights);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function assertRange(array $configuration, string $key, int $min, int $max): void
    {
        $value = $configuration[$key] ?? null;
        if (! is_int($value) || $value < $min || $value > $max) {
            throw RecommendationException::invalid($key.' is outside the allowed range.');
        }
    }
}
