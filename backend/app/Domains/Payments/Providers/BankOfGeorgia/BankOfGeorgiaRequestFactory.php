<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\DTOs\CreateProviderPaymentRequestData;
use App\Domains\Payments\DTOs\ProviderPaymentBasketItemData;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;

final class BankOfGeorgiaRequestFactory
{
    private const DECIMAL_PREFIX = '__BOG_DECIMAL__:';

    private const DESCRIPTION_MAX = 120;

    public function __construct(
        private readonly BankOfGeorgiaMoneySerializer $money,
        private readonly BankOfGeorgiaConfigurationValidator $config,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * @return array{headers: array<string, string>, json: string, ttl_minutes: int}
     */
    public function createOrder(CreateProviderPaymentRequestData $request): array
    {
        $this->assertBasket($request);
        $ttl = $this->ttlMinutes($request);
        $locale = str_starts_with(strtolower($request->customerLocale), 'ka') ? 'ka' : 'en';
        $theme = (string) config('payments.providers.bog.theme', 'dark');
        $idempotency = $request->providerIdempotencyKey !== ''
            ? $request->providerIdempotencyKey
            : $request->merchantReference;

        $payload = [
            'callback_url' => $request->callbackUrl,
            'external_order_id' => $request->merchantReference,
            'capture' => 'automatic',
            'application_type' => 'web',
            'ttl' => $ttl,
            'purchase_units' => [
                'currency' => strtoupper($request->currency),
                'total_amount' => $this->decimalToken($request->amountMinor),
                'basket' => $this->basket($request),
            ],
            'redirect_urls' => [
                'success' => $request->returnUrl,
                'fail' => $request->failureReturnUrl ?? $request->returnUrl,
            ],
            'payment_method' => $this->config->allowedMethods(),
        ];

        if ($request->deliveryAmountMinor > 0) {
            $payload['purchase_units']['delivery'] = [
                'amount' => $this->decimalToken($request->deliveryAmountMinor),
            ];
        }

        $tag = trim((string) config('payments.providers.bog.account_tag', ''));
        if ($tag !== '') {
            $payload['config'] = ['account' => ['tag' => $tag]];
        }

        return [
            'headers' => [
                'Accept-Language' => $locale,
                'Theme' => $theme === 'dark' ? 'dark' : 'light',
                'Idempotency-Key' => $idempotency,
                'Content-Type' => 'application/json',
            ],
            'json' => $this->encode($payload),
            'ttl_minutes' => $ttl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $replaced = preg_replace(
            '/"'.preg_quote(self::DECIMAL_PREFIX, '/').'([0-9]+\.[0-9]{2})"/',
            '$1',
            $json,
        );

        return is_string($replaced) ? $replaced : $json;
    }

    private function decimalToken(int $minor): string
    {
        return self::DECIMAL_PREFIX.$this->money->toMajorString($minor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function basket(CreateProviderPaymentRequestData $request): array
    {
        $lines = [];
        foreach ($request->basketItems as $item) {
            if (! $item instanceof ProviderPaymentBasketItemData) {
                continue;
            }
            if ($item->quantity < 1) {
                throw PaymentException::basketMismatch();
            }

            $line = [
                'product_id' => $item->productId,
                'description' => $this->sanitizeDescription($item->description),
                'quantity' => $item->quantity,
                'unit_price' => $this->decimalToken($item->unitPriceMinor),
                'total_price' => $this->decimalToken($item->lineTotalMinor),
            ];
            if ($item->unitDiscountMinor > 0) {
                $line['unit_discount_price'] = $this->decimalToken($item->unitDiscountMinor);
            }
            $image = $this->publicHttpsImage($item->imageUrl);
            if ($image !== null) {
                $line['image'] = $image;
            }
            $lines[] = $line;
        }

        if ($lines === []) {
            throw PaymentException::basketMismatch();
        }

        return $lines;
    }

    private function assertBasket(CreateProviderPaymentRequestData $request): void
    {
        $sum = 0;
        foreach ($request->basketItems as $item) {
            if (! $item instanceof ProviderPaymentBasketItemData) {
                continue;
            }
            $expectedLine = ($item->unitPriceMinor * $item->quantity) - ($item->unitDiscountMinor * $item->quantity);
            if ($expectedLine !== $item->lineTotalMinor) {
                $this->logger->error('bog_basket_line_mismatch', [
                    'provider' => 'bog',
                    'product_id' => $item->productId,
                    'expected_line_minor' => $expectedLine,
                    'line_total_minor' => $item->lineTotalMinor,
                ]);
                throw PaymentException::basketMismatch();
            }
            $sum += $item->lineTotalMinor;
        }

        $expectedGrand = $sum + $request->deliveryAmountMinor;
        if ($expectedGrand !== $request->amountMinor) {
            $this->logger->error('bog_basket_total_mismatch', [
                'provider' => 'bog',
                'basket_plus_delivery_minor' => $expectedGrand,
                'amount_minor' => $request->amountMinor,
            ]);
            throw PaymentException::basketMismatch();
        }
    }

    private function ttlMinutes(CreateProviderPaymentRequestData $request): int
    {
        $min = 2;
        $max = 1440;
        $safety = max(0, (int) config('payments.providers.bog.ttl_safety_margin_minutes', 1));
        $default = max($min, min($max, (int) config('payments.providers.bog.default_ttl_minutes', 15)));

        if ($request->reservationExpiresAt === null) {
            return $default;
        }

        $remainingSeconds = $request->reservationExpiresAt->getTimestamp() - $this->clock->now()->getTimestamp();
        $minutes = intdiv($remainingSeconds, 60) - $safety;
        if ($minutes < $min) {
            throw PaymentException::ttlInsufficient();
        }

        return min($max, $minutes);
    }

    private function sanitizeDescription(string $description): string
    {
        $plain = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') {
            return 'Item';
        }
        if (mb_strlen($plain) <= self::DESCRIPTION_MAX) {
            return $plain;
        }

        return mb_substr($plain, 0, self::DESCRIPTION_MAX);
    }

    private function publicHttpsImage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        if (strtolower((string) $parts['scheme']) !== 'https') {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return null;
        }

        return $url;
    }
}
