<?php

declare(strict_types=1);

namespace App\Domains\Checkout\DTOs;

use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;

final readonly class CheckoutMutationResultData
{
    public function __construct(
        public CheckoutSession $session,
        public ?CheckoutQuote $quote = null,
        public ?string $issuedGuestToken = null,
    ) {}
}
