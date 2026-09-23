<?php

declare(strict_types=1);

namespace App\Domains\Orders\Support;

use Illuminate\Support\Facades\Log;

final class OrderLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('order.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('order.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('order.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function safe(array $context): array
    {
        unset(
            $context['token'],
            $context['raw_token'],
            $context['guest_token'],
            $context['access_token'],
            $context['cookie'],
            $context['email'],
            $context['phone'],
            $context['address'],
            $context['first_name'],
            $context['last_name'],
            $context['authorization'],
            $context['payload'],
            $context['customer_email'],
            $context['customer_phone'],
        );

        return $context;
    }
}
