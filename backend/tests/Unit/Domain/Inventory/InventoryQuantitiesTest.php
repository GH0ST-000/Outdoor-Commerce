<?php

declare(strict_types=1);

use App\Domains\Inventory\ValueObjects\InventoryQuantities;

it('computes unreserved available and low stock from integers', function (): void {
    $qty = new InventoryQuantities(onHand: 20, reserved: 3, safetyStock: 2, reorderPoint: 5);

    expect($qty->unreserved())->toBe(17)
        ->and($qty->availableToSell())->toBe(15)
        ->and($qty->isLowStock())->toBeFalse()
        ->and($qty->isOutOfStock())->toBeFalse();

    $low = new InventoryQuantities(onHand: 5, reserved: 0, safetyStock: 0, reorderPoint: 5);
    expect($low->isLowStock())->toBeTrue()
        ->and($low->availableToSell())->toBe(5);

    $out = new InventoryQuantities(onHand: 2, reserved: 0, safetyStock: 2, reorderPoint: 0);
    expect($out->availableToSell())->toBe(0)
        ->and($out->isOutOfStock())->toBeTrue();
});

it('rejects reserved above on hand', function (): void {
    expect(fn () => new InventoryQuantities(10, 11, 0, 0))
        ->toThrow(InvalidArgumentException::class);
});
