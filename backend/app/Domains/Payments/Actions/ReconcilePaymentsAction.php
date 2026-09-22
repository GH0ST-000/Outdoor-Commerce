<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Services\PaymentReconciliationService;

final class ReconcilePaymentsAction
{
    public function __construct(private readonly PaymentReconciliationService $payments) {}

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int}
     */
    public function execute(?string $attemptPublicId = null): array
    {
        return $this->payments->execute($attemptPublicId);
    }
}
