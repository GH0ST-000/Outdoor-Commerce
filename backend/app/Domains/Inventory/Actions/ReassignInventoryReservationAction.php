<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Support\Carbon;

final class ReassignInventoryReservationAction
{
    public function execute(
        InventoryReservation $reservation,
        string $referenceType,
        string $referenceId,
        \DateTimeInterface $expiresAt,
    ): InventoryReservation {
        $reservation->reference_type = $referenceType;
        $reservation->reference_id = $referenceId;
        $reservation->expires_at = Carbon::parse($expiresAt);
        $reservation->save();

        return $reservation;
    }
}
