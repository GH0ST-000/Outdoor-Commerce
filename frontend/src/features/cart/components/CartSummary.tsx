"use client";

import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { Button } from "@/components/ui/button";
import { useCartCopy } from "@/features/cart/copy";
import { useLocale } from "@/components/locale-provider";
import type { PublicCart } from "@/features/cart/types";
import Link from "next/link";

export function CartSummary({
  cart,
  sticky = false,
  showViewCart = true,
}: {
  cart: PublicCart;
  sticky?: boolean;
  showViewCart?: boolean;
}) {
  const copy = useCartCopy();
  const { locale } = useLocale();
  const localeTag = locale === "ka" ? "ka-GE" : "en";
  const hasItems = cart.item_count > 0;

  return (
    <aside
      className={
        sticky
          ? "rounded-[var(--radius-2xl)] border border-border/60 bg-card p-5 lg:sticky lg:top-24"
          : "space-y-3 border-t border-border/60 pt-4"
      }
    >
      <h2 className={sticky ? "text-lg font-semibold" : "sr-only"}>
        {copy.subtotal}
      </h2>
      <p className="flex items-baseline justify-between gap-3 text-sm">
        <span>{copy.subtotal}</span>
        <PriceDisplay
          currency={cart.totals.currency}
          locale={localeTag}
          amountMinor={cart.totals.items_subtotal_minor}
        />
      </p>
      {cart.totals.discount_total_minor > 0 ? (
        <p className="flex items-baseline justify-between gap-3 text-sm text-[var(--status-warning)]">
          <span>{copy.savings}</span>
          <PriceDisplay
            currency={cart.totals.currency}
            locale={localeTag}
            amountMinor={cart.totals.discount_total_minor}
          />
        </p>
      ) : null}
      <p className="flex items-baseline justify-between gap-3 text-base font-semibold">
        <span>{copy.title}</span>
        <PriceDisplay
          currency={cart.totals.currency}
          locale={localeTag}
          amountMinor={cart.totals.cart_total_minor}
        />
      </p>
      <p className="text-xs text-muted-foreground">{copy.notice}</p>
      {hasItems ? (
        <Button asChild variant="primary" size="lg" fullWidth>
          <Link href="/checkout">{copy.checkout}</Link>
        </Button>
      ) : (
        <Button
          type="button"
          variant="primary"
          size="lg"
          fullWidth
          disabled
          aria-disabled="true"
        >
          {copy.checkout}
        </Button>
      )}
      <p className="text-xs text-muted-foreground">{copy.checkoutSoon}</p>
      {hasItems && showViewCart ? (
        <Button asChild variant="ghost" size="sm" fullWidth>
          <Link href="/cart">{copy.viewCart}</Link>
        </Button>
      ) : null}
    </aside>
  );
}
