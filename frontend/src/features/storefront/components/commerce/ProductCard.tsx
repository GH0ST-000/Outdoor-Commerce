"use client";

import Link from "next/link";
import Image from "next/image";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export function ProductCard({
  product,
  className,
}: {
  product: ProductCardData;
  className?: string;
}) {
  const { locale, t } = useStorefrontCopy();
  const name = product.name[locale];
  const alt = product.imageAlt[locale];

  return (
    <article
      className={cn(
        "group flex h-full flex-col overflow-hidden rounded-xl border border-border/70 bg-card transition-[transform,border-color] duration-[var(--duration-control)] hover:-translate-y-0.5 hover:border-border",
        className,
      )}
    >
      <Link href={product.href} className="relative block aspect-[4/5] overflow-hidden no-underline">
        <Image
          src={product.imageSrc}
          alt={alt}
          fill
          sizes="(max-width: 768px) 50vw, 25vw"
          className="object-cover transition-transform duration-[var(--duration-panel)] group-hover:scale-[1.03]"
        />
        {product.badges?.length ? (
          <div className="absolute top-3 left-3 flex gap-1.5">
            {product.badges.map((badge) => (
              <Badge key={badge} variant="secondary">
                {badge}
              </Badge>
            ))}
          </div>
        ) : null}
      </Link>
      <div className="flex flex-1 flex-col gap-1.5 p-4">
        <p className="text-xs font-medium tracking-wide text-muted-foreground">
          {product.brand}
        </p>
        <h3 className="text-base font-semibold leading-snug">
          <Link href={product.href} className="text-foreground no-underline hover:underline">
            {name}
          </Link>
        </h3>
        {product.attributePreview ? (
          <p className="text-sm text-muted-foreground">
            {product.attributePreview[locale]}
          </p>
        ) : null}
        <div className="mt-auto pt-3">
          <p className="text-sm font-medium">
            {product.priceLabel?.[locale] ?? t.common.priceOnRequest}
          </p>
          <p className="text-xs text-muted-foreground">
            {product.availabilityLabel?.[locale] ?? t.common.comingSoon}
          </p>
        </div>
      </div>
    </article>
  );
}

export function ProductGrid({
  products,
}: {
  products: ProductCardData[];
}) {
  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-5 xl:grid-cols-4">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}
