import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import type { ResponsiveMedia } from "@/features/storefront/media/types";
import type {
  PublicMedia,
  PublicProductCard,
  PublicProductDetail,
} from "@/features/catalog/types/public-catalog";
import type { CatalogProductView } from "@/features/storefront/adapters/catalog-adapter";

const PRESET_ORDER = [
  "card",
  "card_large",
  "detail",
  "thumbnail",
  "zoom",
] as const;
const FORMAT_ORDER = ["webp", "jpeg", "png", "avif"] as const;

function firstSourceUrl(media: PublicMedia | null): string | undefined {
  if (!media?.sources) {
    return undefined;
  }

  for (const format of FORMAT_ORDER) {
    const presets = media.sources[format]?.presets;
    if (!presets) {
      continue;
    }
    for (const preset of PRESET_ORDER) {
      const url = presets[preset]?.url;
      if (url) {
        return url;
      }
    }
    const fallback = Object.values(presets)[0]?.url;
    if (fallback) {
      return fallback;
    }
  }

  return undefined;
}

export function toResponsiveMedia(
  media: PublicMedia | null,
): ResponsiveMedia | undefined {
  if (!media) {
    return undefined;
  }

  return {
    alt: {
      en: media.alt ?? "",
      ka: media.alt ?? "",
    },
    width: media.width,
    height: media.height,
    focalPoint: media.focal_point,
    sources: media.sources as ResponsiveMedia["sources"],
    fallbackSrc: firstSourceUrl(media),
  };
}

export function availabilityCopy(
  status: PublicProductCard["availability"]["status"],
): { en: string; ka: string } {
  switch (status) {
    case "in_stock":
      return { en: "In stock", ka: "მარაგშია" };
    case "low_stock":
      return { en: "Low stock", ka: "მცირე მარაგი" };
    case "out_of_stock":
      return { en: "Out of stock", ka: "არ არის მარაგში" };
    default:
      return { en: "Unavailable", ka: "მიუწვდომელია" };
  }
}

export function toProductCardData(card: PublicProductCard): ProductCardData {
  const name = card.name ?? "";
  const slug = card.slug ?? String(card.id);
  const media = toResponsiveMedia(card.primary_media);

  return {
    id: String(card.id),
    slug,
    brand: card.brand?.name ?? "",
    name: { en: name, ka: name },
    href: card.href || `/products/${slug}`,
    imageSrc: media?.fallbackSrc ?? "/storefront/product-placeholder.svg",
    imageMedia: media,
    imageAlt: {
      en: card.primary_media?.alt ?? name,
      ka: card.primary_media?.alt ?? name,
    },
    badges: card.is_featured ? ["featured"] : undefined,
    pricing: {
      currency: card.price.currency,
      min_amount_minor: card.price.min_final_amount_minor,
      max_amount_minor: card.price.max_final_amount_minor,
      is_range: card.price.is_range,
      base_amount_minor: card.price.min_base_amount_minor,
      final_amount_minor: card.price.min_final_amount_minor,
    },
    availabilityLabel: availabilityCopy(card.availability.status),
    availabilityStatus: card.availability.status,
    defaultVariantId: card.default_variant_id,
    variantCount: card.variant_count,
    purchasable: card.availability.purchasable,
  };
}

export function toProductDetailView(
  detail: PublicProductDetail,
): CatalogProductView {
  const card = toProductCardData({
    id: detail.id,
    name: detail.name,
    slug: detail.slug,
    href: detail.canonical_path,
    brand: detail.brand
      ? {
          id: detail.brand.id,
          name: detail.brand.name,
          slug: detail.brand.slug,
        }
      : null,
    primary_category: detail.primary_category
      ? {
          id: detail.primary_category.id,
          name: detail.primary_category.name,
          slug: detail.primary_category.slug,
        }
      : null,
    primary_media: detail.gallery[0] ?? null,
    price: detail.price,
    availability: detail.availability,
    is_featured: detail.is_featured,
    variant_count: detail.variants.combinations.length,
    default_variant_id: detail.default_variant_id,
    used_fallback: detail.used_fallback,
  });

  const galleryMedia = detail.gallery
    .map((item) => toResponsiveMedia(item))
    .filter((item): item is ResponsiveMedia => item !== undefined);

  return {
    ...card,
    modelNumber: detail.model_number ?? undefined,
    sku: detail.variants.combinations.find((item) => item.is_default)?.sku,
    shortDescription: {
      en: detail.short_description ?? "",
      ka: detail.short_description ?? "",
    },
    description: {
      en: detail.description ?? "",
      ka: detail.description ?? "",
    },
    gallery: galleryMedia
      .map((item) => item.fallbackSrc)
      .filter((item): item is string => Boolean(item)),
    galleryMedia,
    variants: {
      axes: detail.variants.axes.map((axis) => ({
        id: String(axis.id),
        code: axis.code,
        name: { en: axis.name, ka: axis.name },
        type: axis.type === "color" ? "color" : "select",
        values: axis.values.map((value) => {
          const matching = detail.variants.combinations.filter((combination) =>
            combination.attributes.some(
              (attribute) =>
                attribute.code === axis.code &&
                attribute.value.code === value.code,
            ),
          );
          const disabled = matching.length === 0;
          return {
            id: String(value.id),
            code: value.code,
            name: { en: value.name, ka: value.name },
            colorHex: value.color_hex ?? undefined,
            disabled,
          };
        }),
      })),
    },
    specs: [],
    contexts: [],
  };
}
