<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Exceptions\PublicCatalogQueryException;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Illuminate\Http\Request;

final class PublicCatalogContextFactory
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PublicCatalogPricing $pricing,
    ) {}

    public function fromRequest(Request $request): PublicCatalogContextData
    {
        $explicit = $this->explicitLocale($request);
        $locale = $explicit ?? $this->fromAcceptLanguage($request) ?? CatalogLocales::default();

        if (! CatalogLocales::isSupported($locale)) {
            throw PublicCatalogQueryException::unsupportedLocale($locale);
        }

        $currency = strtoupper((string) ($request->query('currency') ?? config('catalog.public.currency', 'GEL')));
        $expected = strtoupper((string) config('catalog.public.currency', 'GEL'));
        if ($currency !== $expected) {
            throw PublicCatalogQueryException::unsupportedCurrency($currency);
        }

        $priceListId = $this->pricing->defaultPublicPriceListId($currency);
        if ($priceListId === null) {
            throw PublicCatalogQueryException::unsupportedCurrency($currency);
        }

        return new PublicCatalogContextData(
            locale: $locale,
            fallbackLocale: CatalogLocales::fallback(),
            currency: $currency,
            priceListId: $priceListId,
            effectiveAt: $this->clock->now(),
        );
    }

    private function explicitLocale(Request $request): ?string
    {
        $candidates = [
            $request->query('locale'),
            $request->headers->get('X-Locale'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $locale = strtolower(trim($candidate));
            if (! CatalogLocales::isSupported($locale)) {
                throw PublicCatalogQueryException::unsupportedLocale($locale);
            }

            return $locale;
        }

        return null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = (string) $request->headers->get('Accept-Language', '');
        if ($header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            $tag = explode('-', $tag)[0];
            if (CatalogLocales::isSupported($tag)) {
                return $tag;
            }
        }

        return null;
    }
}
