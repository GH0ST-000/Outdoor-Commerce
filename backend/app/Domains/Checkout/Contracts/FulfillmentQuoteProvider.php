<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Contracts;

use App\Domains\Checkout\DTOs\FulfillmentQuoteResultData;
use App\Domains\Checkout\Models\CheckoutAddress;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Checkout\Models\PickupLocation;

interface FulfillmentQuoteProvider
{
    public function quote(
        FulfillmentMethod $method,
        string $currency,
        int $itemsSubtotalMinor,
        ?CheckoutAddress $shippingAddress,
        ?PickupLocation $pickupLocation,
    ): FulfillmentQuoteResultData;
}
