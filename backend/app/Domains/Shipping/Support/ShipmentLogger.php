<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

use Illuminate\Support\Facades\Log;

final class ShipmentLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('shipment.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('shipment.'.$event, $this->safe($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error('shipment.'.$event, $this->safe($context));
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
            $context['recipient'],
            $context['internal_note'],
            $context['internal_message'],
            $context['note'],
        );

        return $context;
    }
}
