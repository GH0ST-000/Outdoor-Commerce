<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentProviderCode: string
{
    case Test = 'test';
    case Bog = 'bog';
}
