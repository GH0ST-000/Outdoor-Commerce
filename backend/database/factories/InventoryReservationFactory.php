<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryReservation>
 */
final class InventoryReservationFactory extends Factory
{
    protected $model = InventoryReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_key' => (string) Str::uuid(),
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => 1,
            'status' => InventoryReservationStatus::Active,
            'reference_type' => 'checkout',
            'reference_id' => (string) Str::uuid(),
            'idempotency_key' => 'res-'.Str::uuid(),
            'payload_hash' => hash('sha256', Str::random(16)),
            'expires_at' => now()->addMinutes(15),
            'committed_at' => null,
            'released_at' => null,
            'release_reason' => null,
            'created_by' => null,
        ];
    }
}
