<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Support;

use Illuminate\Support\Facades\Log;

final class RecommendationLogger
{
    /**
     * @param  array<string, mixed>  $metrics
     */
    public function completed(array $metrics): void
    {
        unset($metrics['latitude'], $metrics['longitude'], $metrics['lat'], $metrics['lng'], $metrics['context']);
        Log::info('recommendation.completed', $metrics);
        if (($metrics['gate'] ?? '') === 'recommendations_blocked' && (int) ($metrics['returned'] ?? 0) > 0) {
            Log::warning('recommendation.prohibited_context_returned_products', [
                'placement' => $metrics['placement'] ?? null,
                'profile_version' => $metrics['profile_version'] ?? null,
            ]);
        }
        if ((int) ($metrics['missing_explanation'] ?? 0) > 0) {
            Log::warning('recommendation.missing_explanation', ['count' => $metrics['missing_explanation']]);
        }
    }

    public function warning(string $code, array $context = []): void
    {
        unset($context['latitude'], $context['longitude'], $context['lat'], $context['lng']);
        Log::warning('recommendation.'.$code, $context);
    }
}
