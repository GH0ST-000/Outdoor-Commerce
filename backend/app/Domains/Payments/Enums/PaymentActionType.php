<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentActionType: string
{
    case Redirect = 'redirect';
    case None = 'none';
}
