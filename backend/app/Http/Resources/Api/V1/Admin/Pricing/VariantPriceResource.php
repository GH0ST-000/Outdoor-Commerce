<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Pricing;

use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Queries\AdminPriceIndexQuery;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VariantPrice */
final class VariantPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = is_string($this->getAttribute('admin_locale'))
            ? (string) $this->getAttribute('admin_locale')
            : (CatalogLocales::isSupported((string) $request->query('locale', ''))
                ? (string) $request->query('locale')
                : CatalogLocales::default());

        $at = $this->getAttribute('effective_at');
        $effectiveAt = $at instanceof CarbonImmutable
            ? $at
            : app(Clock::class)->now();

        $presentation = AdminPriceIndexQuery::presentPeriods($this->resource, $effectiveAt);
        $productName = $this->productVariant?->product?->translations
            ->firstWhere('locale', $locale)?->name
            ?? $this->productVariant?->product?->translations->first()?->name;

        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'product_variant_id' => $this->product_variant_id,
            'version' => $this->version,
            'product_id' => $this->productVariant?->product_id,
            'product_name' => $productName,
            'sku' => $this->productVariant?->sku,
            'barcode' => $this->productVariant?->barcode,
            'variant_label' => $this->productVariant?->combination_signature,
            'currency_code' => $this->priceList?->currency_code,
            'current_period' => $presentation['current'] !== null
                ? (new PricePeriodResource($presentation['current']))->resolve()
                : null,
            'scheduled_period' => $presentation['scheduled'] !== null
                ? (new PricePeriodResource($presentation['scheduled']))->resolve()
                : null,
            'effective_amount_minor' => $presentation['effective_amount_minor'],
            'pricing_ready' => $presentation['pricing_ready'],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
