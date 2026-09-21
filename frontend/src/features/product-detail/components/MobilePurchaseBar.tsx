"use client";

import { Button } from "@/components/ui/button";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { AvailabilityStatus } from "@/components/commerce/availability-status";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import type { PublicAvailabilityStatus } from "@/features/catalog/types/public-catalog";

export function MobilePurchaseBar({
  summary,
  price,
  currency,
  locale,
  availabilityStatus,
  availabilityLabel,
  purchasable,
  hasPrice,
  missingLabel,
  actionLabel,
  cartSoonLabel,
  unavailableLabel,
  onPurchase,
}: {
  summary: string;
  price: number | null;
  currency: string;
  locale: string;
  availabilityStatus: PublicAvailabilityStatus;
  availabilityLabel: string;
  purchasable: boolean;
  hasPrice: boolean;
  missingLabel: string;
  actionLabel: string;
  cartSoonLabel: string;
  unavailableLabel: string;
  onPurchase: () => void;
}) {
  const cartReady = isCartEnabled();
  const canSubmit = cartReady && purchasable && hasPrice;
  const label =
    !hasPrice || !purchasable
      ? unavailableLabel
      : cartReady
        ? actionLabel
        : cartSoonLabel;

  return (
    <div
      className="fixed inset-x-0 bottom-0 z-[var(--z-sticky)] border-t border-border bg-card/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgba(28,24,20,0.08)] backdrop-blur-md lg:hidden"
      data-testid="mobile-purchase-bar"
      aria-hidden="true"
    >
      <div className="mx-auto flex max-w-lg items-center gap-3">
        <div className="min-w-0 flex-1">
          <p className="sf-line-clamp-1 text-sm font-medium text-foreground">
            {summary}
          </p>
          <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <PriceDisplay
              className="text-sm font-semibold"
              amountMinor={price}
              currency={currency}
              locale={locale}
              missingLabel={missingLabel}
            />
            <AvailabilityStatus
              status={availabilityStatus}
              label={availabilityLabel}
            />
          </div>
        </div>
        <Button
          type="button"
          variant="primary"
          size="sm"
          disabled={!canSubmit}
          tabIndex={-1}
          onClick={onPurchase}
        >
          {label}
        </Button>
      </div>
    </div>
  );
}
