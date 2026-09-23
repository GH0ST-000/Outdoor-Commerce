<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum OrderActorType: string
{
    case System = 'system';
    case Customer = 'customer';
    case Admin = 'admin';
    case PaymentProvider = 'payment_provider';
}
