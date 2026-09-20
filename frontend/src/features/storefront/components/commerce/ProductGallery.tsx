"use client";

import { useState } from "react";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import { resolveResponsiveMedia } from "@/features/storefront/media/adapters";
import type { ResponsiveMedia } from "@/features/storefront/media/types";
import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";
import { cn } from "@/lib/utils";

export function ProductGallery({
  items,
  alt,
  locale,
  ariaLabel,
}: {
  items: Array<ResponsiveMedia | string>;
  alt: Record<StorefrontLocale, string>;
  locale: StorefrontLocale;
  ariaLabel: string;
}) {
  const resolved = items.map((item) => resolveResponsiveMedia(item, alt));
  const [activeIndex, setActiveIndex] = useState(0);
  const active = resolved[activeIndex] ?? resolved[0];

  if (!active) {
    return null;
  }

  const aspectWidth = active.width ?? 4;
  const aspectHeight = active.height ?? 5;

  return (
    <div>
      <div
        className="relative overflow-hidden rounded-2xl border border-border/70 bg-muted sm:aspect-[5/6]"
        style={{ aspectRatio: `${aspectWidth} / ${aspectHeight}` }}
      >
        <ResponsiveProductImage
          media={active}
          alt={alt}
          locale={locale}
          preset="detail"
          fill
          priority
          sizes="(max-width:1024px) 100vw, 50vw"
          className="object-cover"
        />
      </div>
      {resolved.length > 1 ? (
        <ul className="mt-3 flex gap-2" aria-label={ariaLabel}>
          {resolved.map((item, index) => (
            <li key={`${index}-${item.fallbackSrc ?? "media"}`}>
              <button
                type="button"
                aria-label={`${ariaLabel} ${index + 1}`}
                aria-pressed={activeIndex === index}
                onClick={() => setActiveIndex(index)}
                className={cn(
                  "relative size-16 overflow-hidden rounded-lg border",
                  activeIndex === index
                    ? "border-2 border-foreground"
                    : "border-border",
                )}
              >
                <ResponsiveProductImage
                  media={item}
                  alt={alt}
                  locale={locale}
                  preset="thumbnail"
                  fill
                  sizes="64px"
                  className="object-cover"
                />
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
