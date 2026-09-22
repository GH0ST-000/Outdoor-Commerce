"use client";

import Link from "next/link";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { Button } from "@/components/ui/button";
import { CartIssueList } from "@/features/cart/components/CartIssueList";
import { useCartCopy } from "@/features/cart/copy";
import { toResponsiveMedia } from "@/features/catalog/adapters/public-catalog-adapter";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import { useLocale } from "@/components/locale-provider";
import { cn } from "@/lib/utils";
import type { CartLine } from "@/features/cart/types";

export function CartLineRow({
  line,
  pending = false,
  layout = "drawer",
  onQuantityChange,
  onRemove,
}: {
  line: CartLine;
  pending?: boolean;
  layout?: "drawer" | "page";
  onQuantityChange: (quantity: number) => void;
  onRemove: () => void;
}) {
  const copy = useCartCopy();
  const { locale } = useLocale();
  const href = line.product.href ?? "/catalog";
  const media = toResponsiveMedia(line.product.primary_media);
  const issueId = `cart-line-issues-${line.id}`;
  const isPage = layout === "page";

  return (
    <article
      className={cn(
        "grid gap-3",
        isPage
          ? "grid-cols-[5.5rem_minmax(0,1fr)] border-b border-border/60 py-5 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-start"
          : "grid-cols-[4.5rem_minmax(0,1fr)] border-b border-border/50 py-4",
      )}
    >
      <Link
        href={href}
        className="relative block aspect-square overflow-hidden rounded-lg no-underline"
      >
        <ResponsiveProductImage
          media={media}
          alt={{ en: line.product.name, ka: line.product.name }}
          locale={locale}
          preset="thumbnail"
          fill
          sizes="112px"
          className="object-cover"
        />
      </Link>
      <div className="min-w-0">
        {line.product.brand?.name ? (
          <p className="text-xs text-muted-foreground">
            {line.product.brand.name}
          </p>
        ) : null}
        <p className="truncate text-sm font-semibold">
          <Link href={href} className="no-underline hover:underline">
            {line.product.name}
          </Link>
        </p>
        {line.variant.label ? (
          <p className="truncate text-sm text-muted-foreground">
            {line.variant.label}
          </p>
        ) : null}
        {isPage && line.variant.sku ? (
          <p className="text-xs text-muted-foreground">
            {copy.sku}: {line.variant.sku}
          </p>
        ) : null}
        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
          <PriceDisplay
            currency={line.pricing.currency}
            locale={locale === "ka" ? "ka-GE" : "en"}
            amountMinor={line.pricing.unit_price_minor}
            baseAmountMinor={
              line.pricing.compare_at_price_minor >
              line.pricing.unit_price_minor
                ? line.pricing.compare_at_price_minor
                : null
            }
            finalAmountMinor={line.pricing.unit_price_minor}
            wasLabel={locale === "ka" ? "იყო" : "Was"}
            nowLabel={locale === "ka" ? "ახლა" : "Now"}
          />
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <QuantityStepper
            value={line.quantity}
            onChange={onQuantityChange}
            min={1}
            max={Math.max(
              line.quantity,
              line.availability.maximum_allowed_quantity,
            )}
            disabled={pending}
            loading={pending}
            label={`${copy.quantity}: ${line.product.name}`}
            decrementLabel={`${copy.quantityDecrease}: ${line.product.name}`}
            incrementLabel={`${copy.quantityIncrease}: ${line.product.name}`}
          />
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onRemove}
            disabled={pending}
            aria-label={`${copy.remove}: ${line.product.name}`}
          >
            {copy.remove}
          </Button>
        </div>
        <CartIssueList issues={line.issues} id={issueId} />
      </div>
      {isPage ? (
        <p className="hidden text-end text-sm font-semibold tabular-nums sm:block">
          <span className="sr-only">{copy.lineTotal}: </span>
          <PriceDisplay
            currency={line.pricing.currency}
            locale={locale === "ka" ? "ka-GE" : "en"}
            amountMinor={line.pricing.line_total_minor}
          />
        </p>
      ) : null}
    </article>
  );
}
