<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

trait RequiresCheckoutIdempotencyKey
{
    public function checkoutIdempotencyKey(): string
    {
        if (! $this instanceof FormRequest) {
            throw new \LogicException('RequiresCheckoutIdempotencyKey must be used on FormRequest classes.');
        }

        $key = trim((string) $this->header('Idempotency-Key', ''));
        $max = (int) config('checkout.idempotency_key_max_length', 128);

        if ($key === '' || strlen($key) > $max) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['A valid Idempotency-Key header is required.'],
            ]);
        }

        return $key;
    }
}
