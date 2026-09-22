<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Support\OrderTokenHasher;

final class OrderAuthorizationService
{
    public function __construct(private readonly OrderTokenHasher $tokens) {}

    public function assertCanAccess(Order $order, OrderActorData $actor): void
    {
        if (! $this->owns($order, $actor)) {
            throw OrderException::notFound();
        }
    }

    public function owns(Order $order, OrderActorData $actor): bool
    {
        if ($actor->userId() !== null) {
            return $order->user_id === $actor->userId();
        }

        if ($order->user_id !== null) {
            return false;
        }

        $raw = $actor->rawOrderToken;
        $hash = $order->access_token_hash;
        if (! is_string($raw) || $raw === '' || ! is_string($hash) || $hash === '') {
            return false;
        }

        return hash_equals($hash, $this->tokens->hash($raw));
    }
}
