<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentEventSource: string
{
    case System = 'system';
    case Admin = 'admin';
    case Carrier = 'carrier';
    case Customer = 'customer';
}
