<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Checkout\Services\CheckoutExpirationService;

final class ExpireCheckoutSessionsAction
{
    public function __construct(private readonly CheckoutExpirationService $expiration) {}

    /**
     * @return array{expired: int}
     */
    public function execute(?int $chunkSize = null): array
    {
        return ['expired' => $this->expiration->expireDueSessions($chunkSize)];
    }
}
