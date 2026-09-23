<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

trait RequiresCartIdempotencyKey
{
    public function cartIdempotencyKey(): string
    {
        if (! $this instanceof FormRequest) {
            throw new \LogicException('RequiresCartIdempotencyKey must be used on FormRequest classes.');
        }

        $key = trim((string) $this->header('Idempotency-Key', ''));
        $max = (int) config('cart.idempotency_key_max_length', 128);

        if ($key === '' || strlen($key) > $max) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['A valid Idempotency-Key header is required.'],
            ]);
        }

        return $key;
    }
}
