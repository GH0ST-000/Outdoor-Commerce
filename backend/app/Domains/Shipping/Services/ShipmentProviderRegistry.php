<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shipping\Contracts\ShipmentProvider;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Providers\Manual\ManualShipmentProvider;

final class ShipmentProviderRegistry
{
    public function resolve(string $code): ShipmentProvider
    {
        $config = config('shipping.providers.'.$code);
        if (! is_array($config)) {
            throw ShipmentException::providerUnknown();
        }

        if (! (bool) ($config['enabled'] ?? false)) {
            throw ShipmentException::providerUnavailable();
        }

        $driver = (string) ($config['driver'] ?? $code);

        return match ($driver) {
            'manual' => app(ManualShipmentProvider::class),
            default => throw ShipmentException::providerUnknown(),
        };
    }
}
