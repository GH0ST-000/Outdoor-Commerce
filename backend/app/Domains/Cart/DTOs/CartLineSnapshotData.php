<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class CartLineSnapshotData
{
    /**
     * @param  list<CartIssueData>  $issues
     */
    public function __construct(
        public string $publicId,
        public int $productId,
        public int $variantId,
        public int $quantity,
        public string $sku,
        public string $productName,
        public string $variantLabel,
        public CartLinePricingData $pricing,
        public CartLineAvailabilityData $availability,
        public array $issues,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->publicId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'sku' => $this->sku,
            'product_name' => $this->productName,
            'variant_label' => $this->variantLabel,
            'pricing' => $this->pricing->toArray(),
            'availability' => $this->availability->toArray(),
            'issues' => array_map(
                static fn (CartIssueData $issue): array => $issue->toArray(),
                $this->issues,
            ),
        ];
    }
}
