import {
  getPublicBrands,
  getPublicCategories,
  getPublicProducts,
} from "@/features/catalog/api/public-catalog-client";
import { toProductCardData } from "@/features/catalog/adapters/public-catalog-adapter";
import type {
  CatalogLocale,
  PublicBrandSummary,
  PublicCategorySummary,
} from "@/features/catalog/types/public-catalog";
import {
  homepageGatewayImages,
  homepageGatewaySlots,
  homepageRevalidateSeconds,
  type HomepageGatewaySlug,
} from "@/features/home/config";
import { getHomePageContent } from "@/features/home/content";
import {
  logStorefrontError,
  logStorefrontWarning,
} from "@/features/storefront/lib/log";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";

const catalogCache = {
  next: {
    revalidate: homepageRevalidateSeconds,
    tags: ["public-catalog"],
  },
};

export type HomeGatewayCategory = {
  slug: string;
  name: string;
  description: string;
  href: string;
  imageSrc: string;
  span: "wide" | "tall" | "standard";
  productCount?: number;
};

export type HomePageData = {
  locale: CatalogLocale;
  gateway: HomeGatewayCategory[];
  categoriesFailed: boolean;
  featured: ProductCardData[] | null;
  featuredFailed: boolean;
  onSale: ProductCardData[] | null;
  onSaleFailed: boolean;
  brands: PublicBrandSummary[] | null;
  brandsFailed: boolean;
};

function flattenCategories(
  nodes: PublicCategorySummary[],
): PublicCategorySummary[] {
  const out: PublicCategorySummary[] = [];
  for (const node of nodes) {
    out.push(node);
    if (node.children?.length) {
      out.push(...flattenCategories(node.children));
    }
  }
  return out;
}

function asSettled<T>(
  result: PromiseSettledResult<T>,
  label: string,
): T | null {
  if (result.status === "fulfilled") {
    return result.value;
  }
  logStorefrontError(`Homepage section failed: ${label}`, {
    reason:
      result.reason instanceof Error
        ? result.reason.message
        : String(result.reason),
  });
  return null;
}

export async function getHomePageData(
  locale: CatalogLocale,
): Promise<HomePageData> {
  const content = getHomePageContent(locale);
  const options = { locale, ...catalogCache };

  const [categoriesResult, featuredResult, onSaleResult, brandsResult] =
    await Promise.allSettled([
      getPublicCategories(options),
      getPublicProducts(
        { featured: true, sort: "featured", per_page: 8 },
        options,
      ),
      getPublicProducts(
        { on_sale: true, sort: "newest", per_page: 8 },
        options,
      ),
      getPublicBrands({ per_page: 8 }, options),
    ]);

  const categoriesEnvelope = asSettled(categoriesResult, "categories");
  const featuredEnvelope = asSettled(featuredResult, "featured");
  const onSaleEnvelope = asSettled(onSaleResult, "on_sale");
  const brandsEnvelope = asSettled(brandsResult, "brands");

  const categoriesFailed = categoriesEnvelope === null;
  const tree = flattenCategories(categoriesEnvelope?.data ?? []);
  const bySlug = new Map(tree.map((item) => [item.slug, item]));

  const gateway: HomeGatewayCategory[] = [];
  for (const slot of homepageGatewaySlots) {
    const match = bySlug.get(slot.slug);
    if (!match) {
      if (!categoriesFailed) {
        logStorefrontWarning("Configured homepage category is not public", {
          slug: slot.slug,
        });
      }
      continue;
    }
    const slug = slot.slug as HomepageGatewaySlug;
    gateway.push({
      slug: match.slug,
      name: match.name ?? slot.slug,
      description: content.gateway[slot.slug]?.description ?? "",
      href: match.path ?? `/catalog/${match.slug}`,
      imageSrc: homepageGatewayImages[slug],
      span: slot.span,
    });
  }

  return {
    locale,
    gateway,
    categoriesFailed,
    featured: featuredEnvelope
      ? (featuredEnvelope.data ?? []).map(toProductCardData)
      : null,
    featuredFailed: featuredEnvelope === null,
    onSale: onSaleEnvelope
      ? (onSaleEnvelope.data ?? [])
          .filter((item) => item.price.on_sale)
          .map(toProductCardData)
      : null,
    onSaleFailed: onSaleEnvelope === null,
    brands: brandsEnvelope ? (brandsEnvelope.data ?? []) : null,
    brandsFailed: brandsEnvelope === null,
  };
}
