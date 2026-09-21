"use client";

import { useRef, useState, type TouchEvent } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import { ProductGalleryViewer } from "@/features/product-detail/components/ProductGalleryViewer";
import { resolveResponsiveMedia } from "@/features/storefront/media/adapters";
import { PRODUCT_IMAGE_PLACEHOLDER } from "@/features/storefront/media/placeholder";
import type { ResponsiveMedia } from "@/features/storefront/media/types";
import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";
import { cn } from "@/lib/utils";

export function ProductGallery({
  items,
  alt,
  locale,
  ariaLabel,
  previousLabel = "Previous image",
  nextLabel = "Next image",
  openLabel = "View larger image",
  closeLabel = "Close image viewer",
  missingLabel,
}: {
  items: Array<ResponsiveMedia | string>;
  alt: Record<StorefrontLocale, string>;
  locale: StorefrontLocale;
  ariaLabel: string;
  previousLabel?: string;
  nextLabel?: string;
  openLabel?: string;
  closeLabel?: string;
  missingLabel?: string;
}) {
  const resolved = items.map((item) => resolveResponsiveMedia(item, alt));
  const itemKey = resolved
    .map((item) => item.fallbackSrc ?? item.alt[locale] ?? "")
    .join("|");
  const [activeIndex, setActiveIndex] = useState(0);
  const [seenKey, setSeenKey] = useState(itemKey);
  const [viewerOpen, setViewerOpen] = useState(false);
  const touchStartX = useRef<number | null>(null);

  if (seenKey !== itemKey) {
    const previous = resolved[Math.min(activeIndex, resolved.length - 1)];
    const kept = previous
      ? resolved.findIndex((item) => item.fallbackSrc === previous.fallbackSrc)
      : -1;
    setSeenKey(itemKey);
    setActiveIndex(kept >= 0 ? kept : 0);
  }

  const count = resolved.length;
  const safeIndex = count === 0 ? 0 : Math.min(activeIndex, count - 1);
  const active = resolved[safeIndex] ?? resolved[0];
  const aspectWidth = active?.width ?? 4;
  const aspectHeight = active?.height ?? 5;
  const canSlide = count > 1;

  function goTo(index: number) {
    if (count === 0) {
      return;
    }
    setActiveIndex((index + count) % count);
  }

  function onTouchStart(event: TouchEvent<HTMLDivElement>) {
    touchStartX.current = event.changedTouches[0]?.clientX ?? null;
  }

  function onTouchEnd(event: TouchEvent<HTMLDivElement>) {
    if (touchStartX.current === null || !canSlide) {
      return;
    }
    const endX = event.changedTouches[0]?.clientX ?? touchStartX.current;
    const delta = endX - touchStartX.current;
    touchStartX.current = null;
    if (delta > 40) {
      goTo(safeIndex - 1);
    } else if (delta < -40) {
      goTo(safeIndex + 1);
    }
  }

  if (!active) {
    return (
      <div
        className="flex aspect-[4/5] items-center justify-center overflow-hidden rounded-[1.5rem] border border-border/60 bg-muted"
        role="img"
        aria-label={missingLabel ?? ariaLabel}
      >
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={PRODUCT_IMAGE_PLACEHOLDER}
          alt=""
          className="size-full object-cover"
        />
      </div>
    );
  }

  return (
    <div>
      <div
        className="relative cursor-zoom-in overflow-hidden rounded-[1.5rem] border border-border/60 bg-muted shadow-[var(--shadow-raised)] sm:aspect-[5/6]"
        style={{ aspectRatio: `${aspectWidth} / ${aspectHeight}` }}
        role="region"
        aria-roledescription="carousel"
        aria-label={ariaLabel}
        tabIndex={canSlide ? 0 : undefined}
        onKeyDown={(event) => {
          if (!canSlide) {
            return;
          }
          if (event.key === "ArrowLeft") {
            event.preventDefault();
            goTo(safeIndex - 1);
          }
          if (event.key === "ArrowRight") {
            event.preventDefault();
            goTo(safeIndex + 1);
          }
        }}
        onTouchStart={onTouchStart}
        onTouchEnd={onTouchEnd}
      >
        <button
          type="button"
          className="absolute inset-0 z-[1]"
          aria-label={openLabel}
          onClick={() => setViewerOpen(true)}
        />
        <ResponsiveProductImage
          media={active}
          alt={active.alt.en || active.alt.ka ? active.alt : alt}
          locale={locale}
          preset="detail"
          fill
          priority={safeIndex === 0}
          sizes="(max-width:1024px) 100vw, 50vw"
          className="object-cover"
        />
        {canSlide ? (
          <>
            <button
              type="button"
              className="absolute top-1/2 left-3 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60"
              aria-label={previousLabel}
              onClick={() => goTo(safeIndex - 1)}
            >
              <ChevronLeft className="size-5" aria-hidden />
            </button>
            <button
              type="button"
              className="absolute top-1/2 right-3 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60"
              aria-label={nextLabel}
              onClick={() => goTo(safeIndex + 1)}
            >
              <ChevronRight className="size-5" aria-hidden />
            </button>
            <p
              className="pointer-events-none absolute right-3 bottom-3 z-10 rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium text-white tabular-nums"
              aria-live="polite"
            >
              {safeIndex + 1} / {count}
            </p>
          </>
        ) : null}
      </div>
      {canSlide ? (
        <ul
          className="mt-3 hidden gap-2 overflow-x-auto pb-1 md:flex"
          aria-label={ariaLabel}
        >
          {resolved.map((item, index) => (
            <li key={`${index}-${item.fallbackSrc ?? "media"}`}>
              <button
                type="button"
                aria-label={`${ariaLabel} ${index + 1}`}
                aria-pressed={safeIndex === index}
                onClick={() => goTo(index)}
                className={cn(
                  "relative size-16 shrink-0 overflow-hidden rounded-lg border",
                  safeIndex === index
                    ? "border-2 border-foreground"
                    : "border-border",
                )}
              >
                <ResponsiveProductImage
                  media={item}
                  alt={{ en: "", ka: "" }}
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
      <ProductGalleryViewer
        open={viewerOpen}
        onOpenChange={setViewerOpen}
        items={resolved}
        index={safeIndex}
        onIndexChange={goTo}
        locale={locale}
        title={ariaLabel}
        closeLabel={closeLabel}
        previousLabel={previousLabel}
        nextLabel={nextLabel}
      />
    </div>
  );
}
