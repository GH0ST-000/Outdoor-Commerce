import { absoluteUrl } from "@/features/catalog/seo/catalog-seo";
import type { PublicBreadcrumb } from "@/features/catalog/types/public-catalog";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import { brandConfig } from "@/features/storefront/config/brand";
import type { CatalogLocale } from "@/features/catalog/types/public-catalog";

export function organizationJsonLd(locale: CatalogLocale) {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: brandConfig.displayName[locale],
    url: absoluteUrl("/"),
  };
}

export function websiteJsonLd(locale: CatalogLocale) {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: brandConfig.displayName[locale],
    url: absoluteUrl("/"),
    inLanguage: locale === "ka" ? "ka-GE" : "en",
  };
}

export function breadcrumbJsonLd(
  items: Array<Pick<PublicBreadcrumb, "name" | "path">>,
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

export function productItemListJsonLd(
  products: ProductCardData[],
  pathname: string,
) {
  return {
    "@context": "https://schema.org",
    "@type": "ItemList",
    url: absoluteUrl(pathname),
    numberOfItems: products.length,
    itemListElement: products.map((product, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: product.name.ka || product.name.en,
      url: absoluteUrl(product.href),
    })),
  };
}
