<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Services\ExpireUnpaidOrdersService;

final class ExpireUnpaidOrdersAction
{
    public function __construct(private readonly ExpireUnpaidOrdersService $orders) {}

    /**
     * @return array{expired: int, skipped: int}
     */
    public function execute(?int $chunkSize = null): array
    {
        return $this->orders->execute($chunkSize);
    }
}
