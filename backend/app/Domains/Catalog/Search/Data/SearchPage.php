<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Data;

use App\Domains\Catalog\Search\Enums\SearchMode;

final readonly class SearchPage
{
    /**
     * @param  list<SearchHit>  $hits
     * @param  array<string, mixed>  $facets
     */
    public function __construct(
        public array $hits,
        public int $total,
        public int $page,
        public int $perPage,
        public SearchMode $mode,
        public bool $fallbackUsed,
        public int $processingTimeMs,
        public array $facets = [],
    ) {}

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        $ids = [];
        foreach ($this->hits as $hit) {
            if (! in_array($hit->productId, $ids, true)) {
                $ids[] = $hit->productId;
            }
        }

        return $ids;
    }
}
