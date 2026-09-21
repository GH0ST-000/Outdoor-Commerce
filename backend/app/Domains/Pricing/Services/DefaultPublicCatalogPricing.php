<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use App\Domains\Pricing\Exceptions\PricingException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;

final class DefaultPublicCatalogPricing implements PublicCatalogPricing
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PriceQuoteService $quotes,
        private readonly PricingCache $pricingCache,
    ) {}

    public function cacheVersion(): int
    {
        return $this->pricingCache->version();
    }

    public function defaultPublicPriceListId(string $currencyCode = 'GEL'): ?int
    {
        $currency = strtoupper($currencyCode);
        $code = (string) config('pricing.default_price_list_code', 'retail_gel');

        $list = PriceList::query()
            ->where('code', $code)
            ->where('currency_code', $currency)
            ->where('status', PriceListStatus::Active->value)
            ->whereNull('deleted_at')
            ->first();

        $list ??= PriceList::query()
            ->where('currency_code', $currency)
            ->where('is_default', true)
            ->where('status', PriceListStatus::Active->value)
            ->whereNull('deleted_at')
            ->first();

        $list ??= PriceList::query()
            ->where('currency_code', $currency)
            ->where('status', PriceListStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        return $list !== null ? (int) $list->id : null;
    }

    public function quoteVariant(int $variantId, int $priceListId, ?CarbonImmutable $effectiveAt = null): ?PublicPriceQuoteData
    {
        try {
            $quote = $this->quotes->quoteVariant($variantId, $priceListId, $effectiveAt);
        } catch (PriceUnavailableException|PricingException) {
            return null;
        }

        $promotions = [];
        foreach ($quote->appliedPromotions as $applied) {
            $code = (string) ($applied['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $promotions[] = [
                'code' => $code,
                'name' => (string) (Promotion::query()->where('code', $code)->value('name') ?? $code),
                'discount_type' => (string) ($applied['discount_type'] ?? ''),
            ];
        }

        return new PublicPriceQuoteData(
            variantId: $quote->variantId,
            priceListId: $quote->priceListId,
            currencyCode: $quote->currencyCode,
            baseAmountMinor: $quote->baseAmountMinor,
            finalAmountMinor: $quote->finalAmountMinor,
            discountAmountMinor: $quote->discountAmountMinor,
            pricingVersion: $quote->pricingVersion,
            calculatedAt: $quote->calculatedAt,
            appliedPromotions: $promotions,
            signature: $quote->pricingSignature,
        );
    }

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicPriceQuoteData>
     */
    public function quoteVariants(array $variantIds, int $priceListId, ?CarbonImmutable $effectiveAt = null): array
    {
        $out = [];
        foreach (array_values(array_unique($variantIds)) as $variantId) {
            $quote = $this->quoteVariant($variantId, $priceListId, $effectiveAt);
            if ($quote !== null) {
                $out[$variantId] = $quote;
            }
        }

        return $out;
    }

    public function nextBoundaryAt(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $at = $from ?? $this->clock->now();

        $priceStart = PricePeriod::query()
            ->where('status', PricePeriodStatus::Published->value)
            ->where('starts_at', '>', $at)
            ->min('starts_at');

        $priceEnd = PricePeriod::query()
            ->where('status', PricePeriodStatus::Published->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $at)
            ->min('ends_at');

        $promoStart = Promotion::query()
            ->where('status', PromotionStatus::Active->value)
            ->where('starts_at', '>', $at)
            ->min('starts_at');

        $promoEnd = Promotion::query()
            ->where('status', PromotionStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $at)
            ->min('ends_at');

        $candidates = array_values(array_filter([$priceStart, $priceEnd, $promoStart, $promoEnd]));
        if ($candidates === []) {
            return null;
        }

        sort($candidates);

        return CarbonImmutable::parse((string) $candidates[0])->utc();
    }

    /**
     * @return list<int>
     */
    public function variantIdsWithBoundaryBetween(CarbonImmutable $from, CarbonImmutable $to, int $afterId = 0, int $limit = 500): array
    {
        $priceVariantIds = VariantPrice::query()
            ->whereIn('id', PricePeriod::query()
                ->select('variant_price_id')
                ->where('status', PricePeriodStatus::Published->value)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereBetween('starts_at', [$from, $to])
                        ->orWhere(function ($inner) use ($from, $to): void {
                            $inner->whereNotNull('ends_at')->whereBetween('ends_at', [$from, $to]);
                        });
                }))
            ->where('product_variant_id', '>', $afterId)
            ->orderBy('product_variant_id')
            ->limit($limit)
            ->pluck('product_variant_id')
            ->all();

        $promotionIds = Promotion::query()
            ->whereIn('status', [PromotionStatus::Active->value, PromotionStatus::Paused->value])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('starts_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->whereNotNull('ends_at')->whereBetween('ends_at', [$from, $to]);
                    });
            })
            ->pluck('id')
            ->all();

        $promoVariantIds = [];
        foreach ($promotionIds as $promotionId) {
            $promoVariantIds = array_merge(
                $promoVariantIds,
                $this->variantIdsTargetedByPromotion((int) $promotionId, $afterId, $limit),
            );
        }

        $ids = array_values(array_unique(array_map('intval', array_merge($priceVariantIds, $promoVariantIds))));
        sort($ids);

        return array_slice($ids, 0, $limit);
    }

    /**
     * @return list<int>
     */
    public function variantIdsTargetedByPromotion(int $promotionId, int $afterId = 0, int $limit = 500): array
    {
        $targets = PromotionTarget::query()
            ->where('promotion_id', $promotionId)
            ->where('mode', PromotionTargetMode::Include->value)
            ->get();

        if ($targets->contains(fn (PromotionTarget $target): bool => $target->target_type === PromotionTargetType::AllProducts)) {
            return ProductVariant::query()
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $variantIds = [];
        $productIds = [];
        $categoryIds = [];
        $brandIds = [];

        foreach ($targets as $target) {
            if ($target->target_id === null) {
                continue;
            }
            match ($target->target_type) {
                PromotionTargetType::ProductVariant => $variantIds[] = (int) $target->target_id,
                PromotionTargetType::Product => $productIds[] = (int) $target->target_id,
                PromotionTargetType::Category => $categoryIds[] = (int) $target->target_id,
                PromotionTargetType::Brand => $brandIds[] = (int) $target->target_id,
                default => null,
            };
        }

        if ($productIds !== []) {
            $variantIds = array_merge(
                $variantIds,
                ProductVariant::query()->whereIn('product_id', $productIds)->pluck('id')->all(),
            );
        }

        if ($brandIds !== []) {
            $variantIds = array_merge(
                $variantIds,
                ProductVariant::query()
                    ->whereIn('product_id', Product::query()->whereIn('brand_id', $brandIds)->select('id'))
                    ->pluck('id')
                    ->all(),
            );
        }

        if ($categoryIds !== []) {
            $variantIds = array_merge(
                $variantIds,
                ProductVariant::query()
                    ->whereIn('product_id', function ($query) use ($categoryIds): void {
                        $query->select('product_id')
                            ->from('category_product')
                            ->whereIn('category_id', $categoryIds);
                    })
                    ->pluck('id')
                    ->all(),
            );
        }

        $ids = array_values(array_unique(array_map('intval', $variantIds)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > $afterId));
        sort($ids);

        return array_slice($ids, 0, $limit);
    }
}
