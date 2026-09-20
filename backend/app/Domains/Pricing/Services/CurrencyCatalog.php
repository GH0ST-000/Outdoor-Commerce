<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;

/**
 * Reads config/currencies.php. Never converts between currencies.
 */
final class CurrencyCatalog
{
    /**
     * @return array{code: string, minor_units: int, symbol: string, enabled: bool}
     */
    public function get(string $code): array
    {
        $code = strtoupper(trim($code));
        /** @var array<string, array{code: string, minor_units: int, symbol: string, enabled: bool}> $currencies */
        $currencies = config('currencies.currencies', []);

        if (! isset($currencies[$code])) {
            throw new InvalidMoneyAmountException("Currency [{$code}] is not configured.");
        }

        return $currencies[$code];
    }

    public function isEnabled(string $code): bool
    {
        try {
            return (bool) $this->get($code)['enabled'];
        } catch (InvalidMoneyAmountException) {
            return false;
        }
    }

    public function assertEnabled(string $code): void
    {
        if (! $this->isEnabled($code)) {
            throw new InvalidMoneyAmountException("Currency [{$code}] is not enabled.");
        }
    }

    public function minorUnits(string $code): int
    {
        return (int) $this->get($code)['minor_units'];
    }

    public function defaultCode(): string
    {
        return (string) config('currencies.default', 'GEL');
    }

    /**
     * @return list<string>
     */
    public function enabledCodes(): array
    {
        /** @var array<string, array{enabled: bool}> $currencies */
        $currencies = config('currencies.currencies', []);

        $codes = [];
        foreach ($currencies as $code => $meta) {
            if (($meta['enabled'] ?? false) === true) {
                $codes[] = strtoupper((string) $code);
            }
        }

        return $codes;
    }
}
