<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;

final readonly class VariantGenerationData
{
    /**
     * @param  array<int, list<int>>  $selection  Axis attribute ID => selected attribute value IDs.
     */
    public function __construct(
        public array $selection,
        public ProductVariantStatus $status = ProductVariantStatus::Draft,
    ) {}
}
