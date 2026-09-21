import { absoluteUrl } from "@/features/catalog/seo/catalog-seo";
import type {
  PublicAvailabilityStatus,
  PublicProductDetail,
  PublicVariantCombination,
} from "@/features/catalog/types/public-catalog";
import {
  firstPublicImageUrl,
  publicImageUrls,
} from "@/features/product-detail/utils/gallery-media";
import { minorToDecimalString } from "@/features/product-detail/utils/money-text";

function schemaAvailability(status: PublicAvailabilityStatus): string {
  switch (status) {
    case "in_stock":
    case "low_stock":
      return "https://schema.org/InStock";
    case "out_of_stock":
      return "https://schema.org/OutOfStock";
    default:
      return "https://schema.org/OutOfStock";
  }
}

function offerForVariant(
  combination: PublicVariantCombination,
  productUrl: string,
) {
  const amount = combination.price.final_amount_minor;
  if (amount == null || !Number.isInteger(amount)) {
    return null;
  }
  return {
    "@type": "Offer",
    url: `${productUrl}?variant=${combination.id}`,
    sku: combination.sku,
    priceCurrency: combination.price.currency,
    price: minorToDecimalString(amount),
    availability: schemaAvailability(combination.availability.status),
  };
}

export function productJsonLd(
  detail: PublicProductDetail,
  locale: "ka" | "en",
) {
  const productUrl = absoluteUrl(detail.canonical_path);
  const images = publicImageUrls(detail.gallery);
  const pricedVariants = detail.variants.combinations.filter(
    (combination) =>
      combination.price.final_amount_minor != null &&
      Number.isInteger(combination.price.final_amount_minor),
  );
  const offers = pricedVariants
    .map((combination) => offerForVariant(combination, productUrl))
    .filter((offer): offer is NonNullable<typeof offer> => offer !== null);

  const defaultVariant =
    pricedVariants.find((item) => item.id === detail.default_variant_id) ??
    pricedVariants[0];

  const finals = pricedVariants.map(
    (combination) => combination.price.final_amount_minor as number,
  );

  const aggregate =
    offers.length > 1
      ? {
          "@type": "AggregateOffer",
          priceCurrency: defaultVariant?.price.currency ?? "GEL",
          lowPrice: minorToDecimalString(Math.min(...finals)),
          highPrice: minorToDecimalString(Math.max(...finals)),
          offerCount: offers.length,
          offers,
        }
      : offers[0];

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: detail.name,
    description: detail.seo.description ?? detail.short_description,
    image: images,
    sku: defaultVariant?.sku,
    brand: detail.brand
      ? { "@type": "Brand", name: detail.brand.name }
      : undefined,
    url: productUrl,
    inLanguage: locale === "ka" ? "ka-GE" : "en",
    offers: aggregate,
  };
}

export function productBreadcrumbJsonLd(
  items: Array<{ name: string; path: string }>,
) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: absoluteUrl(item.path),
    })),
  };
}

export function openGraphImageUrl(
  detail: PublicProductDetail,
): string | undefined {
  return firstPublicImageUrl(
    detail.seo.open_graph_media ?? detail.gallery[0] ?? null,
  );
}
