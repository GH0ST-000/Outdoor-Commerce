<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\Services\CartExpirationService;

final class ExpireCartsAction
{
    public function __construct(private readonly CartExpirationService $expiration) {}

    /**
     * @return array{expired: int, skipped: int}
     */
    public function execute(): array
    {
        return $this->expiration->expireDueCarts();
    }
}
