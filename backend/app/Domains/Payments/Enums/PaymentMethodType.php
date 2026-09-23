<?php

declare(strict_types=1);

namespace App\Domains\Payments\Enums;

enum PaymentMethodType: string
{
    case HostedRedirect = 'hosted_redirect';
}
