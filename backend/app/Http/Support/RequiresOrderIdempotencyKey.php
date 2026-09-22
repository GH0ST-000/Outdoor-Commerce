<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Orders\Exceptions\OrderException;
use Illuminate\Foundation\Http\FormRequest;

trait RequiresOrderIdempotencyKey
{
    public function orderIdempotencyKey(): string
    {
        if (! $this instanceof FormRequest) {
            throw new \LogicException('RequiresOrderIdempotencyKey must be used on FormRequest classes.');
        }

        $key = trim((string) $this->header('Idempotency-Key', ''));
        $max = (int) config('order.idempotency_key_max_length', 128);

        if ($key === '' || strlen($key) > $max) {
            throw OrderException::idempotencyRequired();
        }

        return $key;
    }
}
