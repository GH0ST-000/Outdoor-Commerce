<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

use App\Domains\Cart\Enums\CartStatus;
use Carbon\CarbonImmutable;

final readonly class CartSnapshotData
{
    /**
     * @param  list<CartLineSnapshotData>  $items
     * @param  list<CartIssueData>  $issues
     */
    public function __construct(
        public ?string $publicId,
        public CartStatus $status,
        public int $version,
        public string $currency,
        public int $itemCount,
        public int $uniqueItemCount,
        public array $items,
        public int $itemsSubtotalMinor,
        public int $discountTotalMinor,
        public int $cartTotalMinor,
        public array $issues,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function empty(string $currency): self
    {
        return new self(
            publicId: null,
            status: CartStatus::Active,
            version: 0,
            currency: $currency,
            itemCount: 0,
            uniqueItemCount: 0,
            items: [],
            itemsSubtotalMinor: 0,
            discountTotalMinor: 0,
            cartTotalMinor: 0,
            issues: [],
            updatedAt: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->publicId,
            'status' => $this->status->value,
            'version' => $this->version,
            'currency' => $this->currency,
            'item_count' => $this->itemCount,
            'unique_item_count' => $this->uniqueItemCount,
            'items' => array_map(
                static fn (CartLineSnapshotData $line): array => $line->toArray(),
                $this->items,
            ),
            'totals' => [
                'items_subtotal_minor' => $this->itemsSubtotalMinor,
                'discount_total_minor' => $this->discountTotalMinor,
                'cart_total_minor' => $this->cartTotalMinor,
                'currency' => $this->currency,
            ],
            'issues' => array_map(
                static fn (CartIssueData $issue): array => $issue->toArray(),
                $this->issues,
            ),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
