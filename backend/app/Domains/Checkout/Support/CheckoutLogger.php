<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Support;

use Illuminate\Support\Facades\Log;

final class CheckoutLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('checkout.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('checkout.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('checkout.'.$event, $this->safe($context));
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
            $context['cookie'],
            $context['email'],
            $context['phone'],
            $context['address'],
            $context['first_name'],
            $context['last_name'],
            $context['authorization'],
            $context['payload'],
        );

        return $context;
    }
}
