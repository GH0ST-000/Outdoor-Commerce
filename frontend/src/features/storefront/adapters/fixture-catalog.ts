import type { CatalogProductView } from "@/features/storefront/adapters/catalog-adapter";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import {
  featuredProducts,
  productDetails,
} from "@/features/storefront/fixtures/demo-catalog";

/**
 * Fixture-backed adapter. Swap implementation when public catalog API lands.
 */
export function listFeaturedProducts(): ProductCardData[] {
  return featuredProducts;
}

export function listCatalogProducts(categorySlug?: string): ProductCardData[] {
  if (!categorySlug) return featuredProducts;
  const map: Record<string, string[]> = {
    hunting: ["alpine-hunting-jacket", "ridge-optic-scope"],
    fishing: ["river-braided-line"],
    camping: [],
    clothing: ["alpine-hunting-jacket"],
    optics: ["ridge-optic-scope"],
    knives: ["fixed-blade-knife"],
  };
  const slugs = map[categorySlug] ?? [];
  return featuredProducts.filter((product) => slugs.includes(product.slug));
}

export function getProductBySlug(slug: string): CatalogProductView | null {
  const detail = productDetails[slug];
  if (detail) return detail;
  const card = featuredProducts.find((item) => item.slug === slug);
  if (!card) return null;
  return {
    ...card,
    shortDescription: card.attributePreview ?? { en: "", ka: "" },
    description: {
      en: "Presentation fixture — not live catalog data.",
      ka: "ვიზუალური ფიქსტურა — არ არის ცოცხალი კატალოგი.",
    },
    gallery: [card.imageSrc],
    variants: { axes: [] },
    specs: [],
    contexts: [],
  };
}
