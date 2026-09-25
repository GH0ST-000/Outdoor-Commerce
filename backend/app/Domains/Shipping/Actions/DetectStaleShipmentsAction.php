<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Shipping\Services\DetectStaleShipmentsService;

final class DetectStaleShipmentsAction
{
    public function __construct(private readonly DetectStaleShipmentsService $service) {}

    /**
     * @return list<array{id: string, number: string, status: string, reason: string}>
     */
    public function execute(): array
    {
        return $this->service->execute();
    }
}
