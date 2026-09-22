<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Events\QuoteReservationReleased;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutQuoteLine;
use App\Domains\Checkout\Support\CheckoutLogger;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Shared\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class CheckoutReservationService
{
    public function __construct(
        private readonly CheckoutInventoryService $inventory,
        private readonly CheckoutLogger $logger,
    ) {}

    /**
     * @param  list<array{variant_id: int, quantity: int, item_public_id: string}>  $lines
     * @return array<int, InventoryReservation>
     */
    public function reserveLines(CheckoutQuote $quote, array $lines, \DateTimeInterface $expiresAt): array
    {
        $ordered = $lines;
        usort($ordered, static fn (array $a, array $b): int => $a['variant_id'] <=> $b['variant_id']);

        $reserved = [];
        foreach ($ordered as $line) {
            $data = new ReserveInventoryData(
                productVariantId: $line['variant_id'],
                quantity: $line['quantity'],
                warehouseId: null,
                referenceType: 'checkout_quote',
                referenceId: $quote->public_id,
                expiresAt: $expiresAt,
                idempotencyKey: 'checkout:'.$quote->public_id.':v'.$line['variant_id'],
            );

            try {
                $reserved[$line['variant_id']] = $this->inventory->reserve($data);
            } catch (DomainException $exception) {
                $this->logger->warning('reservation_conflict', [
                    'quote_public_id' => $quote->public_id,
                    'variant_id' => $line['variant_id'],
                    'error_code' => $exception->errorCode(),
                ]);
                throw CheckoutException::insufficientStock([
                    'item_id' => $line['item_public_id'],
                ]);
            }
        }

        $this->logger->info('reservation_success', [
            'quote_public_id' => $quote->public_id,
            'line_count' => count($reserved),
        ]);

        return $reserved;
    }

    public function releaseQuote(CheckoutQuote $quote, string $reason): void
    {
        $keys = CheckoutQuoteLine::query()
            ->where('checkout_quote_id', $quote->id)
            ->whereNotNull('reservation_key')
            ->pluck('reservation_key');

        $reservations = InventoryReservation::query()
            ->where(function ($query) use ($quote, $keys): void {
                $query->where(function ($inner) use ($quote): void {
                    $inner->where('reference_type', 'checkout_quote')
                        ->where('reference_id', $quote->public_id);
                });
                if ($keys->isNotEmpty()) {
                    $query->orWhereIn('reservation_key', $keys->all());
                }
            })
            ->where('status', InventoryReservationStatus::Active)
            ->orderBy('id')
            ->get();

        foreach ($reservations as $reservation) {
            $this->inventory->release(
                $reservation,
                'checkout-release:'.$quote->public_id.':'.$reservation->reservation_key.':'.$reason,
                $reason,
            );
        }

        if ($reservations->isNotEmpty()) {
            $this->logger->info('reservation_release', [
                'quote_public_id' => $quote->public_id,
                'reason' => $reason,
                'count' => $reservations->count(),
            ]);

            DB::afterCommit(function () use ($quote, $reason): void {
                event(new QuoteReservationReleased($quote->public_id, $reason));
            });
        }
    }
}
