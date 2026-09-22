<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\PaymentMethodData;
use App\Domains\Payments\Enums\PaymentMethodType;
use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Exceptions\PaymentException;
use Illuminate\Contracts\Foundation\Application;

final class PaymentMethodRegistry
{
    public function __construct(
        private readonly Application $app,
        private readonly PaymentProviderRegistry $providers,
    ) {}

    /**
     * @return list<PaymentMethodData>
     */
    public function eligibleFor(Order $order, string $locale): array
    {
        $this->providers->assertEnvironment();

        $methods = [];
        $configured = config('payments.methods', []);
        if (! is_array($configured)) {
            return [];
        }

        foreach ($configured as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $method = $this->hydrate($raw, $locale);
            if (! $this->isPubliclyAvailable($method)) {
                continue;
            }
            if (! $this->supportsOrder($method, $order)) {
                continue;
            }
            $methods[] = $method;
        }

        usort($methods, static fn (PaymentMethodData $a, PaymentMethodData $b): int => $a->sortOrder <=> $b->sortOrder);

        return $methods;
    }

    public function requireEligible(Order $order, string $code, string $locale): PaymentMethodData
    {
        foreach ($this->eligibleFor($order, $locale) as $method) {
            if ($method->code === $code) {
                return $method;
            }
        }

        throw PaymentException::methodUnavailable();
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function hydrate(array $raw, string $locale): PaymentMethodData
    {
        $names = is_array($raw['names'] ?? null) ? $raw['names'] : [];
        $descriptions = is_array($raw['descriptions'] ?? null) ? $raw['descriptions'] : [];
        $currencies = is_array($raw['supported_currencies'] ?? null) ? $raw['supported_currencies'] : [];

        return new PaymentMethodData(
            code: (string) ($raw['code'] ?? ''),
            provider: (string) ($raw['provider'] ?? ''),
            type: PaymentMethodType::tryFrom((string) ($raw['type'] ?? '')) ?? PaymentMethodType::HostedRedirect,
            name: (string) ($names[$locale] ?? $names['en'] ?? $raw['code'] ?? ''),
            description: (string) ($descriptions[$locale] ?? $descriptions['en'] ?? ''),
            icon: (string) ($raw['icon'] ?? 'payment'),
            isEnabled: (bool) ($raw['is_enabled'] ?? false),
            developmentOnly: (bool) ($raw['development_only'] ?? false),
            supportedCurrencies: array_values(array_map(static fn (mixed $c): string => (string) $c, $currencies)),
            minimumAmountMinor: isset($raw['minimum_amount_minor']) ? (int) $raw['minimum_amount_minor'] : null,
            maximumAmountMinor: isset($raw['maximum_amount_minor']) ? (int) $raw['maximum_amount_minor'] : null,
            sortOrder: (int) ($raw['sort_order'] ?? 0),
        );
    }

    private function isPubliclyAvailable(PaymentMethodData $method): bool
    {
        if ($method->code === '' || ! $method->isEnabled) {
            return false;
        }

        if ($method->developmentOnly || $method->provider === PaymentProviderCode::Test->value) {
            if ($this->app->environment('production')) {
                return false;
            }
            if (! (bool) config('payments.test.enabled', false)) {
                return false;
            }
        }

        return true;
    }

    private function supportsOrder(PaymentMethodData $method, Order $order): bool
    {
        if (! in_array($order->currency, $method->supportedCurrencies, true)) {
            return false;
        }
        if ($method->minimumAmountMinor !== null && $order->grand_total_minor < $method->minimumAmountMinor) {
            return false;
        }
        if ($method->maximumAmountMinor !== null && $order->grand_total_minor > $method->maximumAmountMinor) {
            return false;
        }

        return true;
    }
}
