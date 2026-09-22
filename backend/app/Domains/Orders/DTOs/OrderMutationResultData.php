<?php

declare(strict_types=1);

namespace App\Domains\Orders\DTOs;

use App\Domains\Orders\Models\Order;

final readonly class OrderMutationResultData
{
    public function __construct(
        public Order $order,
        public bool $created,
        public ?string $issuedOrderToken = null,
        public bool $forgetGuestCartCookie = false,
    ) {}
}
