<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\DTOs\Variants\VariantGenerationData;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\Variants\VariantGenerationService;

/**
 * Read-only: performs no writes, records no audit event, bumps no cache.
 */
final class PreviewVariantGenerationAction
{
    public function __construct(private readonly VariantGenerationService $generation) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Product $product, VariantGenerationData $data): array
    {
        return $this->generation->preview($product, $data);
    }
}
