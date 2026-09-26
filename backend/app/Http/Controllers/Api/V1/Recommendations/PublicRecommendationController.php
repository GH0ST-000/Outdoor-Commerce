<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Recommendations;

use App\Domains\Legal\Actions\VerifyOutdoorContextTokenAction;
use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\Queries\ResolveDerivedLegalContextQuery;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Services\ContextualProductRecommender;
use App\Domains\Recommendations\Services\RecommendationCache;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicRecommendationController extends Controller
{
    public function store(
        Request $request,
        VerifyOutdoorContextTokenAction $tokens,
        ResolveDerivedLegalContextQuery $resolver,
        ContextualProductRecommender $recommender,
        RecommendationCache $cache,
    ): JsonResponse {
        $forbidden = array_intersect(array_keys($request->all()), [
            'gate', 'outcome', 'recommendations_allowed', 'score', 'longitude', 'latitude', 'lng', 'lat', 'coordinate',
        ]);
        if ($forbidden !== []) {
            throw RecommendationException::invalid('Recommendation requests cannot set a legal outcome or coordinates.');
        }

        $data = $request->validate([
            'context_token' => ['nullable', 'string', 'max:8000'],
            'placement' => ['required', 'in:outdoor_context_result,species_detail,season_explorer,map_location_result'],
            'activity' => ['nullable', 'in:hunting,fishing'],
            'species_slug' => ['nullable', 'string', 'max:160'],
            'species_category_code' => ['nullable', 'string', 'max:64'],
            'zone_public_ids' => ['nullable', 'array', 'max:20'],
            'zone_public_ids.*' => ['uuid'],
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d'],
            'season_phase' => ['nullable', 'string', 'max:64'],
            'region_code' => ['nullable', 'string', 'max:16'],
            'method_codes' => ['nullable', 'array', 'max:12'],
            'method_codes.*' => ['string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:10'],
            'locale' => ['nullable', 'in:ka,en'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        if (($data['period_from'] ?? null) xor ($data['period_to'] ?? null)) {
            throw RecommendationException::invalid('A period needs both dates.');
        }

        $locale = $data['locale'] ?? (in_array($request->header('X-Locale'), ['ka', 'en'], true) ? $request->header('X-Locale') : 'ka');
        $placement = RecommendationPlacement::from($data['placement']);
        $context = isset($data['context_token'])
            ? $tokens->execute($data['context_token'])
            : $resolver->execute(
                activity: (string) ($data['activity'] ?? ''),
                speciesSlug: $data['species_slug'] ?? null,
                speciesCategoryCode: $data['species_category_code'] ?? null,
                zonePublicIds: $data['zone_public_ids'] ?? [],
                periodFrom: $data['period_from'] ?? null,
                periodTo: $data['period_to'] ?? null,
                seasonPhase: $data['season_phase'] ?? null,
                regionCode: $data['region_code'] ?? null,
                methodCodes: $data['method_codes'] ?? [],
            );
        if (isset($data['species_category_code']) && $context->speciesCategoryCode === null) {
            $context = $this->withCategory($context, $data['species_category_code']);
        }
        if (isset($data['season_phase']) && $context->seasonPhase === null) {
            $context = $this->withPhase($context, $data['season_phase']);
        }

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? config('recommendations.default_per_page', 10));
        $currency = strtoupper((string) ($data['currency'] ?? config('catalog.public.currency', 'GEL')));
        $cached = $cache->remember([
            'placement' => $placement->value,
            'locale' => $locale,
            'currency' => $currency,
            'page' => $page,
            'per_page' => $perPage,
            'context' => $context->toArray(),
        ], fn (): array => $recommender->recommend($context, $placement, $locale, $currency, $page, $perPage));

        return response()->json([
            'data' => $cached['value'],
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                'cache' => $cached['hit'] ? 'hit' : 'miss',
            ],
        ])->header('Cache-Control', 'private, max-age=30');
    }

    private function withCategory(DerivedLegalContextData $context, string $code): DerivedLegalContextData
    {
        $payload = $context->toArray();
        $payload['species_category_code'] = $code;

        return DerivedLegalContextData::fromArray($payload);
    }

    private function withPhase(DerivedLegalContextData $context, string $phase): DerivedLegalContextData
    {
        $payload = $context->toArray();
        $payload['season_phase'] = $phase;

        return DerivedLegalContextData::fromArray($payload);
    }
}
