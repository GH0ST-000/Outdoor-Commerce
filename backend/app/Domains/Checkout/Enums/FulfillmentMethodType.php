<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Enums;

enum FulfillmentMethodType: string
{
    case StorePickup = 'store_pickup';
    case LocalDelivery = 'local_delivery';
    case CourierDelivery = 'courier_delivery';
}
