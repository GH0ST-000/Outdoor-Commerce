"use client";

import Link from "next/link";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { PromotionBadge } from "@/features/pricing/components/PromotionBadge";
import { AvailabilityStatus } from "@/components/commerce/availability-status";
import { canDirectAddToCart } from "@/features/cart/lib/direct-add";
import { useCartCopy } from "@/features/cart/copy";
import { addItemToCart } from "@/features/cart/state/cart-actions";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import { useState } from "react";

export type ProductLayout = "grid" | "comfortable" | "list";

export function ProductCard({
  product,
  className,
  tone = "default",
  layout = "grid",
}: {
  product: ProductCardData;
  className?: string;
  tone?: "default" | "on-ink";
  layout?: ProductLayout;
}) {
  const { locale, t } = useStorefrontCopy();
  const cartCopy = useCartCopy();
  const name = product.name[locale];
  const onInk = tone === "on-ink";
  const isList = layout === "list";
  const [adding, setAdding] = useState(false);
  const directAdd = isCartEnabled() && canDirectAddToCart(product);

  return (
    <article
      className={cn(
        "sf-lift group overflow-hidden",
        isList ? "flex flex-col sm:flex-row" : "flex h-full flex-col",
        onInk
          ? "rounded-[var(--radius-xl)] border border-white/10 bg-card text-foreground"
          : "rounded-[var(--radius-xl)] border border-border/60 bg-card",
        className,
      )}
    >
      <Link
        href={product.href}
        className={cn(
          "relative block overflow-hidden no-underline",
          isList
            ? "aspect-[16/11] w-full shrink-0 sm:aspect-auto sm:h-auto sm:w-44 md:w-52"
            : "aspect-[4/5]",
        )}
      >
        <ResponsiveProductImage
          media={product.imageMedia ?? product.imageSrc}
          alt={product.imageAlt}
          locale={locale}
          preset="card"
          fill
          sizes={
            isList
              ? "(max-width:640px) 100vw, 208px"
              : "(max-width: 768px) 50vw, 25vw"
          }
          className="object-cover transition-transform duration-[800ms] ease-[var(--ease-emphasized)] group-hover:scale-[1.06]"
        />
        {product.badges?.length ? (
          <div className="absolute top-3 left-3 flex gap-1.5">
            {product.badges.map((badge) => (
              <Badge
                key={badge}
                variant="secondary"
                className="bg-[var(--alpine-slate)]/88 text-[var(--mist)] capitalize backdrop-blur-sm"
              >
                {badge}
              </Badge>
            ))}
          </div>
        ) : null}
      </Link>
      <div
        className={cn(
          "flex flex-1 flex-col gap-1.5",
          isList ? "justify-center p-3 sm:p-5" : "p-3 sm:p-5",
        )}
      >
        <p className="text-xs font-medium tracking-wide text-muted-foreground">
          {product.brand}
        </p>
        <h3
          className={cn(
            "font-semibold leading-snug",
            isList
              ? "sf-line-clamp-2 text-base sm:text-lg"
              : "sf-line-clamp-2 text-sm min-[420px]:min-h-[2.5em] min-[420px]:text-base",
          )}
        >
          <Link
            href={product.href}
            className="text-foreground no-underline hover:underline"
          >
            {name}
          </Link>
        </h3>
        {product.attributePreview ? (
          <p
            className={cn(
              "sf-line-clamp-1 text-sm text-muted-foreground",
              !isList && "min-h-[1.25em]",
            )}
          >
            {product.attributePreview[locale]}
          </p>
        ) : !isList ? (
          <p className="min-h-[1.25em]" />
        ) : null}
        <div
          className={cn(
            "border-t border-border/60 pt-3",
            isList ? "mt-3" : "mt-auto",
          )}
        >
          <p className="type-price text-sm font-medium text-foreground">
            {product.pricing ? (
              <span className="inline-flex flex-wrap items-baseline gap-2">
                <PriceDisplay
                  currency={product.pricing.currency}
                  locale={locale === "ka" ? "ka-GE" : "en"}
                  amountMinor={product.pricing.amount_minor}
                  baseAmountMinor={product.pricing.base_amount_minor}
                  finalAmountMinor={product.pricing.final_amount_minor}
                  minAmountMinor={product.pricing.min_amount_minor}
                  maxAmountMinor={product.pricing.max_amount_minor}
                  isRange={product.pricing.is_range}
                  missingLabel={t.common.priceOnRequest}
                  wasLabel={locale === "ka" ? "იყო" : "Was"}
                  nowLabel={locale === "ka" ? "ახლა" : "Now"}
                />
                {product.pricing.discount_percentage_basis_points != null ? (
                  <PromotionBadge
                    percentageBasisPoints={
                      product.pricing.discount_percentage_basis_points
                    }
                    className="text-xs font-semibold text-[var(--status-warning)]"
                  />
                ) : null}
              </span>
            ) : (
              (product.priceLabel?.[locale] ?? t.common.priceOnRequest)
            )}
          </p>
          <AvailabilityStatus
            status={product.availabilityStatus ?? "unavailable"}
            label={product.availabilityLabel?.[locale] ?? t.common.comingSoon}
          />
          {isCartEnabled() ? (
            directAdd ? (
              <Button
                type="button"
                variant="secondary"
                size="sm"
                className="mt-3"
                loading={adding}
                aria-label={`${cartCopy.addToCart}: ${name}`}
                onClick={() => {
                  if (product.defaultVariantId == null || adding) {
                    return;
                  }
                  setAdding(true);
                  void addItemToCart({
                    variant_id: product.defaultVariantId,
                    quantity: 1,
                  }).finally(() => setAdding(false));
                }}
              >
                {adding ? cartCopy.adding : cartCopy.addToCart}
              </Button>
            ) : (
              <Button asChild variant="ghost" size="sm" className="mt-3">
                <Link
                  href={product.href}
                  aria-label={`${cartCopy.chooseOptions}: ${name}`}
                >
                  {cartCopy.chooseOptions}
                </Link>
              </Button>
            )
          ) : null}
        </div>
      </div>
    </article>
  );
}

export function ProductGrid({
  products,
  tone = "default",
  layout = "grid",
}: {
  products: ProductCardData[];
  tone?: "default" | "on-ink";
  layout?: ProductLayout;
}) {
  return (
    <div
      className={cn(
        layout === "list" && "flex flex-col gap-3",
        layout === "grid" &&
          "grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 md:grid-cols-3 md:gap-5",
        layout === "comfortable" && "grid grid-cols-1 gap-4 sm:grid-cols-2",
      )}
    >
      {products.map((product) => (
        <ProductCard
          key={product.id}
          product={product}
          tone={tone}
          layout={layout}
        />
      ))}
    </div>
  );
}
