"use client";

import Image from "next/image";
import { useState } from "react";
import { resolveResponsiveMedia } from "@/features/storefront/media/adapters";
import { PRODUCT_IMAGE_PLACEHOLDER } from "@/features/storefront/media/placeholder";
import type {
  MediaFormat,
  MediaPreset,
  ResponsiveMedia,
} from "@/features/storefront/media/types";
import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";
import { cn } from "@/lib/utils";

const FORMAT_ORDER: MediaFormat[] = ["webp", "avif", "jpeg", "png"];

function presetEntry(
  media: ResponsiveMedia,
  preset: MediaPreset,
): { url: string; width: number; height: number } | null {
  if (!media.sources) return null;
  for (const format of FORMAT_ORDER) {
    const entry = media.sources[format]?.presets?.[preset];
    if (entry?.url) {
      return entry;
    }
  }
  return null;
}

function objectPosition(media: ResponsiveMedia): string | undefined {
  if (!media.focalPoint) return undefined;
  return `${media.focalPoint.x * 100}% ${media.focalPoint.y * 100}%`;
}

export function ResponsiveProductImage({
  media,
  alt,
  locale,
  preset = "card",
  className,
  fill,
  sizes,
  priority,
}: {
  media: ResponsiveMedia | string | undefined;
  alt: Record<StorefrontLocale, string>;
  locale: StorefrontLocale;
  preset?: MediaPreset;
  className?: string;
  fill?: boolean;
  sizes?: string;
  priority?: boolean;
}) {
  const resolved = resolveResponsiveMedia(media, alt);
  const [failed, setFailed] = useState(false);
  const label = resolved.alt[locale] || alt[locale] || "";
  const entry = presetEntry(resolved, preset);
  const src =
    failed || !entry?.url
      ? (resolved.fallbackSrc ?? PRODUCT_IMAGE_PLACEHOLDER)
      : entry.url;
  const position = objectPosition(resolved);
  const width = entry?.width ?? resolved.width ?? undefined;
  const height = entry?.height ?? resolved.height ?? undefined;

  const webp = resolved.sources?.webp;
  const jpeg = resolved.sources?.jpeg;

  if (webp?.srcset || jpeg?.srcset) {
    return (
      <picture className={cn(fill && "relative block size-full", className)}>
        {webp?.srcset ? (
          <source type="image/webp" srcSet={webp.srcset} sizes={sizes} />
        ) : null}
        {jpeg?.srcset ? (
          <source type="image/jpeg" srcSet={jpeg.srcset} sizes={sizes} />
        ) : null}
        <img
          src={src}
          alt={label}
          className={cn(
            fill && "absolute inset-0 size-full object-cover",
            className,
          )}
          style={position ? { objectPosition: position } : undefined}
          sizes={sizes}
          onError={() => setFailed(true)}
        />
      </picture>
    );
  }

  return (
    <Image
      src={src}
      alt={label}
      fill={fill}
      width={fill ? undefined : (width ?? 640)}
      height={fill ? undefined : (height ?? 800)}
      sizes={sizes}
      priority={priority}
      className={cn(fill && "object-cover", className)}
      style={position ? { objectPosition: position } : undefined}
      onError={() => setFailed(true)}
    />
  );
}
