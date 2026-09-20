<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeadlockRetry
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback, ?string $correlationId = null): mixed
    {
        $max = max(1, (int) config('inventory.max_deadlock_retries', 3));
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (Throwable $exception) {
                if (! $exception instanceof QueryException || ! $this->isDeadlock($exception) || $attempt >= $max) {
                    throw $exception;
                }

                $attempt++;
                Log::warning('inventory.deadlock_retry', [
                    'attempt' => $attempt,
                    'correlation_id' => $correlationId,
                ]);
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
