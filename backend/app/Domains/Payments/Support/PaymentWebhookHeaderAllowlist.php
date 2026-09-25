<?php

declare(strict_types=1);

namespace App\Domains\Payments\Support;

final class PaymentWebhookHeaderAllowlist
{
    /**
     * @var list<string>
     */
    private const ALLOWED = [
        'content-type',
        'callback-signature',
        'x-test-signature',
        'x-test-timestamp',
        'x-correlation-id',
        'user-agent',
    ];

    /**
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, string>
     */
    public function filter(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            if (! in_array($key, self::ALLOWED, true)) {
                continue;
            }
            $safe[$key] = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        return $safe;
    }
}
