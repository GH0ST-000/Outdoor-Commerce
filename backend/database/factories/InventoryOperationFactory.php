<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Models\InventoryOperation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryOperation>
 */
final class InventoryOperationFactory extends Factory
{
    protected $model = InventoryOperation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'type' => InventoryOperationType::Receipt,
            'idempotency_key' => 'test-'.Str::uuid(),
            'payload_hash' => hash('sha256', Str::random(16)),
            'reference_type' => null,
            'reference_id' => null,
            'reason_code' => null,
            'note' => null,
            'performed_by' => null,
            'correlation_id' => null,
            'occurred_at' => now(),
        ];
    }
}
