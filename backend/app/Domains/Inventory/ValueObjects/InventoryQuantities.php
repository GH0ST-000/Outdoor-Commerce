<?php

declare(strict_types=1);

namespace App\Domains\Inventory\ValueObjects;

/**
 * Derived inventory quantities. Prefer calculating these over persisting them.
 */
final readonly class InventoryQuantities
{
    public function __construct(
        public int $onHand,
        public int $reserved,
        public int $safetyStock,
        public int $reorderPoint,
    ) {
        if ($onHand < 0 || $reserved < 0 || $safetyStock < 0 || $reorderPoint < 0) {
            throw new \InvalidArgumentException('Inventory quantities cannot be negative.');
        }
        if ($reserved > $onHand) {
            throw new \InvalidArgumentException('Reserved quantity cannot exceed on-hand.');
        }
    }

    public function unreserved(): int
    {
        return $this->onHand - $this->reserved;
    }

    public function availableToSell(): int
    {
        return max(0, $this->onHand - $this->reserved - $this->safetyStock);
    }

    public function isLowStock(): bool
    {
        return $this->availableToSell() <= $this->reorderPoint;
    }

    public function isOutOfStock(): bool
    {
        return $this->availableToSell() === 0;
    }

    /**
     * @return array{
     *   on_hand: int,
     *   reserved: int,
     *   unreserved: int,
     *   safety_stock: int,
     *   available_to_sell: int,
     *   reorder_point: int
     * }
     */
    public function toArray(): array
    {
        return [
            'on_hand' => $this->onHand,
            'reserved' => $this->reserved,
            'unreserved' => $this->unreserved(),
            'safety_stock' => $this->safetyStock,
            'available_to_sell' => $this->availableToSell(),
            'reorder_point' => $this->reorderPoint,
        ];
    }
}
