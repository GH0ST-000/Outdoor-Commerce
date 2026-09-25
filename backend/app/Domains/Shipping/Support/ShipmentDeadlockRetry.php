<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

use Illuminate\Database\QueryException;
use Throwable;

final class ShipmentDeadlockRetry
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $max = max(1, (int) config('shipping.max_deadlock_retries', 3));
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (Throwable $exception) {
                if (! $exception instanceof QueryException || ! $this->isDeadlock($exception) || $attempt >= $max) {
                    throw $exception;
                }

                $attempt++;
            }
        }
    }

    private function isDeadlock(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $code === '40001'
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout');
    }
}
