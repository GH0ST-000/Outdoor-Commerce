<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Contracts;

interface ProductPricingReadiness
{
    /**
     * @return array<string, list<string>>
     */
    public function warningsForProduct(int $productId): array;
}
