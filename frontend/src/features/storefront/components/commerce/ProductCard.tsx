"use client";

import Link from "next/link";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { PromotionBadge } from "@/features/pricing/components/PromotionBadge";

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
  const name = product.name[locale];
  const onInk = tone === "on-ink";
  const isList = layout === "list";

  return (
    <article
      className={cn(
        "group overflow-hidden transition-[transform,border-color,box-shadow] duration-[var(--duration-control)]",
        isList
          ? "flex flex-col sm:flex-row"
          : "flex h-full flex-col hover:-translate-y-1",
        onInk
          ? "rounded-[1.25rem] border border-white/10 bg-[color-mix(in_oklab,var(--warm-bone)_96%,white)] text-[var(--charcoal)] shadow-[0_18px_40px_-28px_rgba(0,0,0,0.65)]"
          : "rounded-[1.25rem] border border-border/70 bg-card",
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
          className="object-cover transition-transform duration-[700ms] ease-[var(--ease-out)] group-hover:scale-[1.04]"
        />
        {product.badges?.length ? (
          <div className="absolute top-3 left-3 flex gap-1.5">
            {product.badges.map((badge) => (
              <Badge
                key={badge}
                variant="secondary"
                className="bg-[var(--warm-bone)]/92 text-[var(--charcoal)] capitalize backdrop-blur-sm"
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
        <p
          className={cn(
            "text-xs font-medium tracking-wide",
            onInk
              ? "text-[color-mix(in_oklab,var(--charcoal)_55%,transparent)]"
              : "text-muted-foreground",
          )}
        >
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
            className={cn(
              "no-underline hover:underline",
              onInk ? "text-[var(--charcoal)]" : "text-foreground",
            )}
          >
            {name}
          </Link>
        </h3>
        {product.attributePreview ? (
          <p
            className={cn(
              "sf-line-clamp-1 text-sm",
              !isList && "min-h-[1.25em]",
              onInk
                ? "text-[color-mix(in_oklab,var(--charcoal)_55%,transparent)]"
                : "text-muted-foreground",
            )}
          >
            {product.attributePreview[locale]}
          </p>
        ) : !isList ? (
          <p className="min-h-[1.25em]" />
        ) : null}
        <div
          className={cn(
            "border-t pt-3",
            isList ? "mt-3" : "mt-auto",
            onInk
              ? "border-[color-mix(in_oklab,var(--charcoal)_10%,transparent)]"
              : "border-border/60",
          )}
        >
          <p
            className={cn(
              "text-sm font-medium",
              onInk ? "text-[var(--charcoal)]" : "text-foreground",
            )}
          >
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
                />
                {product.pricing.discount_percentage_basis_points != null ? (
                  <PromotionBadge
                    percentageBasisPoints={
                      product.pricing.discount_percentage_basis_points
                    }
                    className="text-xs font-semibold text-[var(--pine)]"
                  />
                ) : null}
              </span>
            ) : (
              (product.priceLabel?.[locale] ?? t.common.priceOnRequest)
            )}
          </p>
          <p
            className={cn(
              "sf-line-clamp-1 text-xs",
              onInk
                ? "text-[color-mix(in_oklab,var(--charcoal)_52%,transparent)]"
                : "text-muted-foreground",
            )}
          >
            {product.availabilityLabel?.[locale] ?? t.common.comingSoon}
          </p>
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
