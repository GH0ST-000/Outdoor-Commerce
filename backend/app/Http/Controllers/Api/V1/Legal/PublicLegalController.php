<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Legal;

use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Queries\GetSpeciesLegalOverviewQuery;
use App\Domains\Legal\Services\LegalPresenter;
use App\Domains\Legal\Services\LegalPublicCache;
use App\Domains\Legal\Services\LegalRuleEvaluator;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Legal\PublicEvaluateLegalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicLegalController
{
    public function sources(Request $request, LegalPresenter $presenter, LegalPublicCache $cache): JsonResponse
    {
        $payload = $cache->remember('legal.sources', ['locale' => (string) $request->header('X-Locale', 'ka')], function () use ($presenter): array {
            return LegalSource::query()
                ->where('is_active', true)
                ->where('verification_status', LegalVerificationStatus::Verified)
                ->with(['authority', 'documents.currentVersion'])
                ->orderBy('name')
                ->get()
                ->map(fn (LegalSource $source): array => $presenter->sourcePublic($source))
                ->filter()
                ->values()
                ->all();
        });

        return $this->ok($request, $payload);
    }

    public function showSource(Request $request, string $slug, LegalPresenter $presenter, LegalPublicCache $cache): JsonResponse
    {
        $payload = $cache->remember('legal.source', ['slug' => $slug], function () use ($slug, $presenter): array {
            $source = LegalSource::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->where('verification_status', LegalVerificationStatus::Verified)
                ->with(['authority', 'documents.currentVersion'])
                ->first();
            if ($source === null) {
                throw LegalException::notFound('Legal source');
            }

            return $presenter->sourcePublic($source);
        });

        return $this->ok($request, $payload);
    }

    public function speciesOverview(Request $request, string $slug, GetSpeciesLegalOverviewQuery $query): JsonResponse
    {
        $species = Species::query()->published()->where('canonical_slug', $slug)->first()
            ?? throw SpeciesException::notFound();
        $jurisdiction = (string) ($request->query('jurisdiction') ?: config('legal.default_jurisdiction'));

        return $this->ok($request, $query->forSpecies($species, $jurisdiction));
    }

    public function evaluate(PublicEvaluateLegalRequest $request, LegalRuleEvaluator $evaluator): JsonResponse
    {
        $result = $evaluator->evaluate($request->facts());
        unset($result['trace']);

        return $this->ok($request, $result);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function ok(Request $request, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
