<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogCache;
use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCatalogResponder
{
    public function __construct(
        private readonly PublicCatalogCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function cached(
        Request $request,
        PublicCatalogContextData $context,
        string $resource,
        array $params,
        callable $producer,
    ): JsonResponse {
        $payload = $this->cache->remember($context, $resource, $params, $producer);

        return $this->respond($request, $context, $payload);
    }

    public function respond(Request $request, PublicCatalogContextData $context, mixed $payload): JsonResponse
    {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
        $etag = hash('sha256', $encoded.'|'.$context->locale.'|'.$context->currency);
        $ttl = $this->cache->ttlSeconds($context);
        $swr = (int) config('catalog.public.http.stale_while_revalidate_seconds', 300);

        $response = response()->json(
            $payload,
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )
            ->setEtag($etag)
            ->header('Content-Language', $context->locale)
            ->header('Vary', 'Accept-Language, Accept-Encoding, X-Locale')
            ->header('Cache-Control', "public, max-age={$ttl}, stale-while-revalidate={$swr}");

        $requestId = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);
        if (is_string($requestId) && $requestId !== '') {
            $response->headers->set(CorrelationId::HEADER, $requestId);
        }

        if ($response->isNotModified($request)) {
            $response->setContent(null);
        }

        return $response;
    }
}
