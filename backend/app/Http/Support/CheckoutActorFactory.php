<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Checkout\DTOs\CheckoutActorData;
use Illuminate\Http\Request;

final class CheckoutActorFactory
{
    public function __construct(private readonly CartActorFactory $carts) {}

    public function fromRequest(
        Request $request,
        ?string $idempotencyKey = null,
        ?string $endpoint = null,
        ?int $expectedSessionVersion = null,
        ?int $expectedCartVersion = null,
    ): CheckoutActorData {
        $cartActor = $this->carts->fromRequest($request, $idempotencyKey, $endpoint);

        return new CheckoutActorData(
            cartActor: $cartActor,
            idempotencyKey: $idempotencyKey,
            endpoint: $endpoint,
            expectedSessionVersion: $expectedSessionVersion,
            expectedCartVersion: $expectedCartVersion,
        );
    }
}
