import { toResponsiveMedia } from "@/features/catalog/adapters/public-catalog-adapter";
import type { PublicMedia } from "@/features/catalog/types/public-catalog";
import type { PublicVariantCombination } from "@/features/catalog/types/public-catalog";
import type { ResponsiveMedia } from "@/features/storefront/media/types";

export function mediaForSelectedVariant(
  productGallery: PublicMedia[],
  variant: PublicVariantCombination | null,
): ResponsiveMedia[] {
  const source =
    variant && variant.media.length > 0 ? variant.media : productGallery;
  return source
    .map((item) => toResponsiveMedia(item))
    .filter((item): item is ResponsiveMedia => item !== undefined);
}

export function firstPublicImageUrl(
  media: PublicMedia | null | undefined,
): string | undefined {
  if (!media?.sources) {
    return undefined;
  }
  for (const format of ["jpeg", "webp", "png"]) {
    const presets = media.sources[format]?.presets;
    if (!presets) {
      continue;
    }
    for (const preset of ["detail", "card_large", "card", "thumbnail"]) {
      const url = presets[preset]?.url;
      if (url) {
        return url;
      }
    }
  }
  return undefined;
}

export function publicImageUrls(media: PublicMedia[]): string[] {
  return media
    .map((item) => firstPublicImageUrl(item))
    .filter((item): item is string => Boolean(item));
}
