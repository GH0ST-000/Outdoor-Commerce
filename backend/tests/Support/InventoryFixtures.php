<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Testing\TestResponse;

final class InventoryFixtures
{
    public static function defaultWarehouse(array $attributes = []): Warehouse
    {
        return Warehouse::factory()->default()->create($attributes);
    }

    public static function secondWarehouse(array $attributes = []): Warehouse
    {
        return Warehouse::factory()->create(array_merge([
            'code' => 'TBS-02',
            'name' => 'Tbilisi Secondary',
            'is_default' => false,
        ], $attributes));
    }

    public static function activeVariant(): ProductVariant
    {
        return ProductVariant::factory()->active()->create();
    }

    public static function idempotencyHeader(string $key = 'test-idem-1'): array
    {
        return ['Idempotency-Key' => $key, 'Accept' => 'application/json'];
    }

    public static function assertJsonErrorCode(TestResponse $response, string $code, int $status): void
    {
        $response->assertStatus($status)->assertJsonPath('error.code', $code);
    }
}
