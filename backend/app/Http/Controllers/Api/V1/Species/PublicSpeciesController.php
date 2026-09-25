<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Species;

use App\Domains\Hunting\Queries\GetPublicSpeciesQuery;
use App\Domains\Hunting\Queries\PublicSpeciesFiltersQuery;
use App\Domains\Hunting\Services\SpeciesPublicCache;
use App\Domains\Hunting\Services\SpeciesSearchQueryService;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Species\PublicSpeciesIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicSpeciesController
{
    public function __construct(
        private readonly SpeciesSearchQueryService $search,
        private readonly GetPublicSpeciesQuery $detail,
        private readonly PublicSpeciesFiltersQuery $filters,
        private readonly SpeciesPublicCache $cache,
    ) {}

    public function index(PublicSpeciesIndexRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $locale = $filters['locale'];
        $started = microtime(true);

        $payload = $this->cache->remember($locale, 'species.index', $filters, function () use ($filters, $request): array {
            $result = $this->search->search($filters);
            $page = (int) ($filters['page'] ?? 1);
            $perPage = (int) ($filters['per_page'] ?? 10);
            $total = (int) $result['total'];

            return [
                'data' => $result['cards'],
                'meta' => [
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
                    ],
                    'filters' => $filters,
                    'locale' => $filters['locale'],
                    'search_mode' => $result['search_mode'],
                    'fallback_used' => $result['fallback_used'],
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });

        return $this->cachedResponse($request, $locale, $payload, $started);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $this->locale($request);
        $started = microtime(true);
        $payload = $this->cache->remember($locale, 'species.show', ['slug' => $slug], function () use ($slug, $locale, $request): array {
            return [
                'data' => $this->detail->bySlug($slug, $locale),
                'meta' => [
                    'locale' => $locale,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });

        return $this->cachedResponse($request, $locale, $payload, $started);
    }

    public function similar(Request $request, string $slug): JsonResponse
    {
        $locale = $this->locale($request);
        $payload = $this->cache->remember($locale, 'species.similar', ['slug' => $slug], function () use ($slug, $locale, $request): array {
            $detail = $this->detail->bySlug($slug, $locale);

            return [
                'data' => $detail['identification']['similar_species'] ?? [],
                'meta' => [
                    'locale' => $locale,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });

        return $this->cachedResponse($request, $locale, $payload);
    }

    public function filters(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $payload = $this->cache->remember($locale, 'species.filters', [], function () use ($locale, $request): array {
            return [
                'data' => $this->filters->all($locale),
                'meta' => [
                    'locale' => $locale,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });

        return $this->cachedResponse($request, $locale, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cachedResponse(Request $request, string $locale, array $payload, ?float $started = null): JsonResponse
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $etag = hash('sha256', $encoded.'|'.$locale);
        $ttl = $this->cache->ttlSeconds();
        $swr = (int) config('species.cache.stale_while_revalidate_seconds', 300);

        $response = response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->setEtag($etag)
            ->header('Content-Language', $locale)
            ->header('Vary', 'Accept-Language, Accept-Encoding, X-Locale')
            ->header('Cache-Control', "public, max-age={$ttl}, stale-while-revalidate={$swr}");

        if ($started !== null) {
            $response->headers->set('X-Species-Timing', (string) ((int) round((microtime(true) - $started) * 1000)));
        }

        if ($response->isNotModified($request)) {
            $response->setContent(null);
        }

        return $response;
    }

    private function locale(Request $request): string
    {
        $locale = (string) ($request->query('locale') ?: $request->header('X-Locale', SpeciesLocales::default()));

        return SpeciesLocales::isSupported($locale) ? $locale : SpeciesLocales::default();
    }
}
