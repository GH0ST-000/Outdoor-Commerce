"use client";

import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { PromotionBadge } from "@/features/pricing/components/PromotionBadge";
import type { PublicVariantCombination } from "@/features/catalog/types/public-catalog";
import type { PublicProductPriceRange } from "@/features/catalog/types/public-catalog";

export function ProductPricePanel({
  variant,
  productPrice,
  locale,
  missingLabel,
  wasLabel,
  nowLabel,
}: {
  variant: PublicVariantCombination | null;
  productPrice: PublicProductPriceRange;
  locale: string;
  missingLabel: string;
  wasLabel: string;
  nowLabel: string;
}) {
  const promotion = variant?.price.applied_promotions[0];
  const numberLocale = locale === "ka" ? "ka-GE" : "en-GE";

  if (!variant) {
    return (
      <div className="min-h-[2.5rem]">
        <PriceDisplay
          isRange={productPrice.is_range}
          minAmountMinor={productPrice.min_final_amount_minor}
          maxAmountMinor={productPrice.max_final_amount_minor}
          currency={productPrice.currency}
          locale={numberLocale}
          missingLabel={missingLabel}
        />
      </div>
    );
  }

  return (
    <div className="min-h-[2.5rem] space-y-1">
      <PriceDisplay
        className="text-2xl font-semibold tracking-tight text-[var(--sand)]"
        currency={variant.price.currency}
        locale={numberLocale}
        baseAmountMinor={variant.price.base_amount_minor}
        finalAmountMinor={variant.price.final_amount_minor}
        missingLabel={missingLabel}
        wasLabel={wasLabel}
        nowLabel={nowLabel}
      />
      {promotion?.name ? (
        <PromotionBadge
          label={promotion.name}
          className="inline-flex rounded-full bg-[var(--status-warning)]/12 px-2.5 py-0.5 text-xs font-medium text-[var(--status-warning)]"
        />
      ) : null}
    </div>
  );
}
