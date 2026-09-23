<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Shared\Support\Clock;

final class ConvertCheckoutSessionAction
{
    public function __construct(private readonly Clock $clock) {}

    public function execute(CheckoutSession $session, int $orderId): void
    {
        $session->status = CheckoutSessionStatus::Converted;
        $session->converted_order_id = $orderId;
        $session->version = $session->version + 1;
        $session->last_activity_at = $this->clock->now();
        $session->save();
    }
}
