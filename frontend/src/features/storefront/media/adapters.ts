import type { ResponsiveMedia } from "@/features/storefront/media/types";
import type { StorefrontLocale } from "@/features/storefront/types/storefront-types";

export function responsiveMediaFromUrl(
  src: string,
  alt: Record<StorefrontLocale, string>,
): ResponsiveMedia {
  return {
    alt,
    fallbackSrc: src,
  };
}

export function resolveResponsiveMedia(
  media: ResponsiveMedia | string | undefined,
  alt: Record<StorefrontLocale, string>,
): ResponsiveMedia {
  if (typeof media === "string") {
    return responsiveMediaFromUrl(media, alt);
  }
  if (media) {
    return media;
  }
  return { alt };
}
