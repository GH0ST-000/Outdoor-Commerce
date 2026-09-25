<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

use App\Domains\Shipping\Exceptions\ShipmentException;

final class TrackingUrlValidator
{
    public function validate(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        $max = max(1, (int) config('shipping.tracking_url_max_length', 2048));
        if (strlen($trimmed) > $max || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw ShipmentException::trackingUrlInvalid();
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw ShipmentException::trackingUrlInvalid();
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if (in_array($scheme, ['javascript', 'data', 'file', 'vbscript'], true)) {
            throw ShipmentException::trackingUrlInvalid();
        }

        $allowedSchemes = config('shipping.allowed_tracking_schemes', ['https']);
        if (! is_array($allowedSchemes) || ! in_array($scheme, $allowedSchemes, true)) {
            throw ShipmentException::trackingUrlInvalid();
        }

        if ((bool) config('shipping.https_required', false) && $scheme !== 'https') {
            throw ShipmentException::trackingUrlInvalid();
        }

        $configured = config('shipping.allowed_tracking_hosts', []);
        $allowedHosts = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            is_array($configured) ? $configured : [],
        )));

        if ($allowedHosts === [] || ! in_array($host, $allowedHosts, true)) {
            throw ShipmentException::trackingUrlInvalid();
        }

        return $trimmed;
    }
}
