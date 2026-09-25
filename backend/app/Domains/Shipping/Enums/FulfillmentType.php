<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum FulfillmentType: string
{
    case Delivery = 'delivery';
    case StorePickup = 'store_pickup';
}
