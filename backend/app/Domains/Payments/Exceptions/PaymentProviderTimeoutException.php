<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

final class PaymentProviderTimeoutException extends PaymentException
{
    public function __construct()
    {
        parent::__construct(
            'The payment provider did not respond in time. The attempt will be reconciled.',
            'PAYMENT_PROVIDER_TIMEOUT',
        );
    }
}
