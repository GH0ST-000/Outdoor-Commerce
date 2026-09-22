<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Queries\GetPublicProductsQuery;
use App\Domains\Catalog\Search\Data\SearchPage;
use App\Jobs\SyncProductSearchDocuments;
use Illuminate\Support\Facades\Log;

final class SearchResultHydrator
{
    public function __construct(
        private readonly GetPublicProductsQuery $products,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function hydrate(PublicCatalogContextData $context, SearchPage $page): array
    {
        $ids = $page->productIds();
        if ($ids === []) {
            return [];
        }

        $cards = $this->products->cardsByOrderedIds($context, $ids);
        $found = [];
        foreach ($cards as $card) {
            $found[(int) $card['id']] = true;
        }

        foreach ($ids as $id) {
            if (! isset($found[$id])) {
                Log::notice('search.stale_hit', [
                    'module' => 'search',
                    'action' => 'stale_hit',
                    'product_id' => $id,
                ]);
                SyncProductSearchDocuments::dispatch($id);
            }
        }

        return $cards;
    }
}
