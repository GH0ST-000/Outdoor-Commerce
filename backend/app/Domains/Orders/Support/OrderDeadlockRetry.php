<?php

declare(strict_types=1);

namespace App\Domains\Orders\Support;

use Illuminate\Database\QueryException;
use Throwable;

final class OrderDeadlockRetry
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $max = max(1, (int) config('order.max_deadlock_retries', 3));
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

    public function isDeadlock(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $code === '40001'
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout');
    }

    public function isUniqueConstraint(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $code === '23000'
            || $code === '19'
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
