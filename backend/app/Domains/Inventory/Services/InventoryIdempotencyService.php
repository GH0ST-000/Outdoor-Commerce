<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Exceptions\IdempotencyConflictException;
use App\Domains\Inventory\Models\InventoryOperation;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class InventoryIdempotencyService
{
    public function claimOperation(
        string $idempotencyKey,
        string $payloadHash,
        InventoryOperationType $type,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reasonCode,
        ?string $note,
        ?int $performedBy,
        ?string $correlationId,
    ): OperationClaim {
        $existing = InventoryOperation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            $this->assertMatchingHash($existing->payload_hash, $payloadHash);

            return OperationClaim::replay($existing);
        }

        try {
            $operation = InventoryOperation::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason_code' => $reasonCode,
                'note' => $note,
                'performed_by' => $performedBy,
                'correlation_id' => $correlationId,
                'occurred_at' => now(),
            ]);

            return OperationClaim::fresh($operation);
        } catch (QueryException) {
            $existing = InventoryOperation::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
            $this->assertMatchingHash($existing->payload_hash, $payloadHash);

            return OperationClaim::replay($existing);
        }
    }

    public function claimReservation(
        string $idempotencyKey,
        string $payloadHash,
    ): ?InventoryReservation {
        $existing = InventoryReservation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            return null;
        }

        $this->assertMatchingHash($existing->payload_hash, $payloadHash);

        return $existing;
    }

    private function assertMatchingHash(?string $stored, string $incoming): void
    {
        if ($stored !== null && $stored !== $incoming) {
            throw new IdempotencyConflictException;
        }
    }
}
