<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Catalog\Contracts\SearchGateway;
use App\Domains\Catalog\Models\Product;
use App\Domains\Recommendations\Models\ContextTaxonomyTerm;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MySQL assignments are the candidate baseline. Meilisearch may add ids and
 * never decides eligibility. Every id is revalidated by the recommender.
 */
final class CandidateGenerator
{
    public bool $usedFallback = false;

    public function __construct(private readonly SearchGateway $search) {}

    /**
     * @param  array<string, list<string>>  $codesByDimension
     * @return array{ids: list<int>, sources: array{mysql: int, meilisearch: int, meilisearch_fallback: bool}}
     */
    public function collect(array $codesByDimension, int $limit, Carbon $at): array
    {
        $limit = max(1, $limit);
        $termQuery = ContextTaxonomyTerm::query()->where('is_active', true);
        $termQuery->where(function ($query) use ($codesByDimension): void {
            $applied = false;
            foreach ($codesByDimension as $dimension => $codes) {
                if ($codes === []) {
                    continue;
                }
                $applied = true;
                $query->orWhere(function ($inner) use ($dimension, $codes): void {
                    $inner->where('dimension', $dimension)->whereIn('code', $codes);
                });
            }
            if (! $applied) {
                $query->whereRaw('1 = 0');
            }
        });
        $terms = $termQuery->get(['id', 'catalog_category_id']);
        $termIds = $terms->pluck('id')->all();
        $mysqlIds = [];
        if ($termIds !== []) {
            $mysqlIds = ProductContextAssignment::query()
                ->activeNow($at)
                ->whereIn('context_taxonomy_term_id', $termIds)
                ->whereIn('assignment_type', ['required_match', 'preferred_match', 'supported'])
                ->distinct()
                ->limit($limit)
                ->pluck('product_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $categoryIds = $terms->pluck('catalog_category_id')->filter()->map(static fn ($id): int => (int) $id)->all();
            if ($categoryIds !== []) {
                $extra = Product::query()
                    ->whereIn('primary_category_id', $categoryIds)
                    ->where('status', 'active')
                    ->limit($limit)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();
                $mysqlIds = array_values(array_unique([...$mysqlIds, ...$extra]));
            }
        }

        $searchIds = [];
        $this->usedFallback = false;
        $uid = (string) config('search.index_prefix', 'outdoor').'_'.(string) config('recommendations.search_index', 'recommendation_context');
        try {
            if (! $this->search->health() || ! $this->search->indexExists($uid)) {
                $this->usedFallback = true;
            } else {
                $result = $this->search->search($uid, '', [
                    'limit' => $limit,
                    'filter' => [],
                ]);
                foreach ($result['hits'] ?? [] as $hit) {
                    if (is_array($hit) && isset($hit['product_id'])) {
                        $searchIds[] = (int) $hit['product_id'];
                    }
                }
            }
        } catch (Throwable $exception) {
            $this->usedFallback = true;
            Log::warning('recommendation.meilisearch_fallback', ['message' => $exception->getMessage()]);
        }

        $ids = array_slice(array_values(array_unique([...$mysqlIds, ...$searchIds])), 0, $limit);

        return [
            'ids' => $ids,
            'sources' => [
                'mysql' => count($mysqlIds),
                'meilisearch' => count($searchIds),
                'meilisearch_fallback' => $this->usedFallback,
            ],
        ];
    }
}
