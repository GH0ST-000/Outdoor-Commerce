<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

final readonly class PreviewPromotionData
{
    /**
     * @param  list<int>  $variantIds
     * @param  list<PromotionTargetData>|null  $targets
     */
    public function __construct(
        public ?int $priceListId = null,
        public array $variantIds = [],
        public ?PromotionWriteData $promotion = null,
        public ?array $targets = null,
        public ?int $existingPromotionId = null,
    ) {}
}
