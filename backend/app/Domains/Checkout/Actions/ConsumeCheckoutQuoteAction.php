<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Shared\Support\Clock;

final class ConsumeCheckoutQuoteAction
{
    public function __construct(private readonly Clock $clock) {}

    public function execute(CheckoutQuote $quote): void
    {
        $quote->status = CheckoutQuoteStatus::Consumed;
        $quote->consumed_at = $this->clock->now();
        $quote->save();
    }
}
