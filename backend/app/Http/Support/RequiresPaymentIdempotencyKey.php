<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Payments\Exceptions\PaymentException;
use Illuminate\Foundation\Http\FormRequest;

trait RequiresPaymentIdempotencyKey
{
    public function paymentIdempotencyKey(): string
    {
        if (! $this instanceof FormRequest) {
            throw new \LogicException('RequiresPaymentIdempotencyKey must be used on FormRequest classes.');
        }

        $key = trim((string) $this->header('Idempotency-Key', ''));
        $max = (int) config('payments.idempotency_key_max_length', 128);

        if ($key === '' || strlen($key) > $max) {
            throw PaymentException::idempotencyRequired();
        }

        return $key;
    }
}
