"use client";

import { useEffect, useId } from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from "@/components/ui/dialog";
import { IconButton } from "@/components/ui/icon-button";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import type { ResponsiveMedia } from "@/features/storefront/media/types";
import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";
import { cn } from "@/lib/utils";

export function ProductGalleryViewer({
  open,
  onOpenChange,
  items,
  index,
  onIndexChange,
  locale,
  title,
  closeLabel,
  previousLabel,
  nextLabel,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  items: ResponsiveMedia[];
  index: number;
  onIndexChange: (index: number) => void;
  locale: StorefrontLocale;
  title: string;
  closeLabel: string;
  previousLabel: string;
  nextLabel: string;
}) {
  const captionId = useId();
  const active = items[index];
  const caption = active?.alt[locale] || title;

  useEffect(() => {
    if (!open) {
      return;
    }
    function onKey(event: KeyboardEvent) {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        onIndexChange((index - 1 + items.length) % items.length);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        onIndexChange((index + 1) % items.length);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [index, items.length, onIndexChange, open]);

  if (!active) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        showClose={false}
        closeLabel={closeLabel}
        className="top-0 left-0 flex h-[100dvh] max-h-[100dvh] w-full max-w-none translate-x-0 translate-y-0 flex-col rounded-none border-0 bg-[color-mix(in_oklab,var(--night-forest)_94%,black)] p-0 text-white"
        aria-describedby={captionId}
      >
        <DialogTitle className="sr-only">{title}</DialogTitle>
        <DialogDescription id={captionId} className="sr-only">
          {caption}
        </DialogDescription>
        <div className="flex items-center justify-between gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2">
          <p className="text-sm tabular-nums text-white/80">
            {index + 1} / {items.length}
          </p>
          <DialogClose asChild>
            <IconButton
              label={closeLabel}
              variant="ghost"
              className="text-white"
            >
              <X />
            </IconButton>
          </DialogClose>
        </div>
        <div className="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
          {items.length > 1 ? (
            <button
              type="button"
              className="absolute top-1/2 left-3 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/45"
              aria-label={previousLabel}
              onClick={() =>
                onIndexChange((index - 1 + items.length) % items.length)
              }
            >
              <ChevronLeft className="size-5" aria-hidden />
            </button>
          ) : null}
          <div className="relative h-full max-h-[82dvh] w-full max-w-5xl">
            {open ? (
              <ResponsiveProductImage
                media={active}
                alt={active.alt}
                locale={locale}
                preset="zoom"
                fill
                sizes="100vw"
                className="object-contain"
              />
            ) : null}
          </div>
          {items.length > 1 ? (
            <button
              type="button"
              className="absolute top-1/2 right-3 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/45"
              aria-label={nextLabel}
              onClick={() => onIndexChange((index + 1) % items.length)}
            >
              <ChevronRight className="size-5" aria-hidden />
            </button>
          ) : null}
        </div>
        {items.length > 1 ? (
          <ul className="flex justify-center gap-2 overflow-x-auto px-4 pb-4">
            {items.map((item, thumbIndex) => (
              <li key={`${thumbIndex}-${item.fallbackSrc ?? "media"}`}>
                <button
                  type="button"
                  aria-current={thumbIndex === index ? "true" : undefined}
                  aria-label={`${thumbIndex + 1}`}
                  onClick={() => onIndexChange(thumbIndex)}
                  className={cn(
                    "relative size-14 overflow-hidden rounded-md border",
                    thumbIndex === index ? "border-white" : "border-white/30",
                  )}
                >
                  <ResponsiveProductImage
                    media={item}
                    alt={item.alt}
                    locale={locale}
                    preset="thumbnail"
                    fill
                    sizes="56px"
                    className="object-cover"
                  />
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}
