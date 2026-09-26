<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Exceptions\RecommendationException;

/**
 * Deterministic weighted score normalized to 0–100.
 * Absent context dimensions are omitted from the denominator.
 * An activity-only match is capped so it cannot look like an exact fit.
 */
final class RecommendationScorer
{
    /**
     * @param  array<string, float>  $signals
     * @param  array<string, int>  $weights
     * @param  list<string>  $activeDimensions
     * @return array{score: int, contributions: list<array{dimension: string, signal: float, weight: int, points: float}>}
     */
    public function score(array $signals, array $weights, array $activeDimensions, int $activityOnlyCap, int $penalty, bool $activityOnly = false): array
    {
        $this->assertWeights($weights);
        $contributions = [];
        $weighted = 0.0;
        $denominator = 0;

        foreach ($activeDimensions as $dimension) {
            $weight = $weights[$dimension] ?? 0;
            if ($weight <= 0) {
                continue;
            }
            $signal = max(0.0, min(1.0, (float) ($signals[$dimension] ?? 0)));
            $points = $signal * $weight;
            $weighted += $points;
            $denominator += $weight;
            $contributions[] = [
                'dimension' => $dimension,
                'signal' => $signal,
                'weight' => $weight,
                'points' => round($points, 4),
            ];
        }

        $score = $denominator === 0 ? 0 : (int) round(($weighted / $denominator) * 100);
        if ($activityOnly) {
            $score = min($score, $activityOnlyCap);
        }
        $score = max(0, min(100, $score - min(20, $penalty)));

        return ['score' => $score, 'contributions' => $contributions];
    }

    /**
     * @param  array<string, int>  $weights
     */
    public function assertWeights(array $weights): void
    {
        $expected = config('recommendations.dimensions', []);
        $min = (int) config('recommendations.weight_min', 0);
        $max = (int) config('recommendations.weight_max', 40);
        $sumTarget = (int) config('recommendations.weight_sum', 100);
        $unknown = array_diff(array_keys($weights), $expected);
        $missing = array_diff($expected, array_keys($weights));
        if ($unknown !== [] || $missing !== []) {
            throw RecommendationException::invalid('Ranking weights must include every allowed dimension and no others.', [
                'unknown' => array_values($unknown),
                'missing' => array_values($missing),
            ]);
        }
        $sum = 0;
        foreach ($weights as $dimension => $weight) {
            if ($weight < $min || $weight > $max) {
                throw RecommendationException::invalid('Weight for '.$dimension.' is outside the allowed range.');
            }
            $sum += $weight;
        }
        if ($sum !== $sumTarget) {
            throw RecommendationException::invalid('Ranking weights must sum to '.$sumTarget.'.');
        }
    }
}
