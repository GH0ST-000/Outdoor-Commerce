<?php

declare(strict_types=1);

namespace App\Domains\Payments\Support;

use Illuminate\Support\Facades\Log;

final class PaymentLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('payment.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('payment.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('payment.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function critical(string $event, array $context = []): void
    {
        Log::critical('payment.'.$event, $this->safe($context));
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
            $context['secret'],
            $context['signature'],
            $context['authorization'],
            $context['cookie'],
            $context['email'],
            $context['phone'],
            $context['address'],
            $context['payload'],
            $context['raw_body'],
            $context['redirect_url'],
            $context['customer_email'],
            $context['customer_phone'],
            $context['webhook_secret'],
        );

        return $context;
    }
}
