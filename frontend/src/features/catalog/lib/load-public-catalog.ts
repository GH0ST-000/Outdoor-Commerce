import { cache } from "react";
import { ApiClientError } from "@/lib/api-client";
import {
  getPublicBrands,
  getPublicBrand,
  getPublicCategories,
  getPublicCategory,
  getPublicProduct,
  getPublicProductFacets,
  getPublicProducts,
} from "@/features/catalog/api/public-catalog-client";
import {
  toProductCardData,
  toProductDetailView,
} from "@/features/catalog/adapters/public-catalog-adapter";
import type {
  CatalogLocale,
  PublicBrandDetail,
  PublicCategoryDetail,
  PublicCategorySummary,
  PublicFacets,
  PublicProductListParams,
} from "@/features/catalog/types/public-catalog";
import type { CatalogProductView } from "@/features/storefront/adapters/catalog-adapter";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";
import {
  catalogQueryToListParams,
  emptyCatalogQuery,
  type CatalogQuery,
} from "@/features/catalog/query-state/catalog-search-params";
import { logStorefrontError } from "@/features/storefront/lib/log";

const revalidate = {
  next: { revalidate: 60, tags: ["public-catalog"] },
};

export type CatalogListState = {
  products: ProductCardData[];
  total: number;
  page: number;
  perPage: number;
  lastPage: number;
  sort: string;
  facets: PublicFacets | null;
  categories: PublicCategorySummary[];
  title: string;
  lead: string;
  error: { code: string; message: string } | null;
  fallbackUsed?: boolean;
  searchMode?: string;
};

function asError(error: unknown): { code: string; message: string } {
  if (error instanceof ApiClientError) {
    return { code: error.code, message: error.message };
  }
  return { code: "SERVER_ERROR", message: "The catalog could not be loaded." };
}

export function emptyCatalogListState(
  error: { code: string; message: string } | null = null,
): CatalogListState {
  return {
    products: [],
    total: 0,
    page: 1,
    perPage: 10,
    lastPage: 1,
    sort: "featured",
    facets: null,
    categories: [],
    title: "",
    lead: "",
    error,
  };
}

export async function loadCatalogList(
  params: PublicProductListParams,
  locale: CatalogLocale,
): Promise<CatalogListState> {
  const [productsResult, facetsResult, categoriesResult] =
    await Promise.allSettled([
      getPublicProducts(
        { ...params, per_page: params.per_page ?? 10 },
        { locale, ...revalidate },
      ),
      getPublicProductFacets(params, { locale, ...revalidate }),
      getPublicCategories({ locale, ...revalidate }),
    ]);

  if (productsResult.status === "rejected") {
    logStorefrontError("Catalog product list failed", {
      reason:
        productsResult.reason instanceof Error
          ? productsResult.reason.message
          : String(productsResult.reason),
    });
  }
  if (facetsResult.status === "rejected") {
    logStorefrontError("Catalog facets failed", {
      reason:
        facetsResult.reason instanceof Error
          ? facetsResult.reason.message
          : String(facetsResult.reason),
    });
  }

  const products =
    productsResult.status === "fulfilled" ? productsResult.value : null;
  const facets =
    facetsResult.status === "fulfilled" ? facetsResult.value : null;
  const categories =
    categoriesResult.status === "fulfilled" ? categoriesResult.value : null;

  return {
    products: (products?.data ?? []).map(toProductCardData),
    total: products?.meta?.pagination?.total ?? 0,
    page: products?.meta?.pagination?.current_page ?? params.page ?? 1,
    perPage: products?.meta?.pagination?.per_page ?? params.per_page ?? 10,
    lastPage: products?.meta?.pagination?.last_page ?? 1,
    sort: products?.meta?.sort ?? params.sort ?? "featured",
    facets: facets?.data ?? null,
    categories: categories?.data ?? [],
    title: "",
    lead: "",
    error:
      productsResult.status === "rejected"
        ? asError(productsResult.reason)
        : null,
    fallbackUsed: Boolean(
      products?.meta?.fallback_used ?? products?.meta?.used_fallback,
    ),
    searchMode: products?.meta?.search_mode,
  };
}

export async function loadCatalogListing(
  query: CatalogQuery,
  locale: CatalogLocale,
  extras: { category?: string; brand?: string } = {},
): Promise<CatalogListState> {
  const params = catalogQueryToListParams(query, {
    locale,
    category: extras.category,
    brand:
      extras.brand && query.brand.length === 0
        ? [extras.brand]
        : query.brand.length > 0
          ? query.brand
          : undefined,
  });
  return loadCatalogList(params, locale);
}

export async function loadCategoryDetail(
  slug: string,
  locale: CatalogLocale,
): Promise<
  | { category: PublicCategoryDetail }
  | { category: null }
  | { error: { code: string; message: string } }
> {
  try {
    const response = await getPublicCategory(slug, { locale, ...revalidate });
    return { category: response.data };
  } catch (error) {
    if (error instanceof ApiClientError && error.status === 404) {
      return { category: null };
    }
    return { error: asError(error) };
  }
}

export async function loadBrandDetail(
  slug: string,
  locale: CatalogLocale,
): Promise<
  | { brand: PublicBrandDetail }
  | { brand: null }
  | { error: { code: string; message: string } }
> {
  try {
    const response = await getPublicBrand(slug, { locale, ...revalidate });
    return { brand: response.data };
  } catch (error) {
    if (error instanceof ApiClientError && error.status === 404) {
      return { brand: null };
    }
    return { error: asError(error) };
  }
}

export const loadProductDetail = cache(async function loadProductDetail(
  slug: string,
  locale: CatalogLocale,
): Promise<
  | {
      product: CatalogProductView;
      detail: Awaited<ReturnType<typeof getPublicProduct>>["data"];
    }
  | "not_found"
  | { error: { code: string; message: string } }
> {
  try {
    const response = await getPublicProduct(slug, { locale, ...revalidate });
    return {
      product: toProductDetailView(response.data),
      detail: response.data,
    };
  } catch (error) {
    if (error instanceof ApiClientError && error.status === 404) {
      return "not_found";
    }
    return { error: asError(error) };
  }
});

export async function loadRelatedProducts(
  locale: CatalogLocale,
  excludeId: number,
  categorySlug?: string | null,
): Promise<ProductCardData[]> {
  const fetchList = async (params: PublicProductListParams) => {
    const response = await getPublicProducts(params, { locale, ...revalidate });
    return (response.data ?? [])
      .filter((item) => item.id !== excludeId)
      .slice(0, 4)
      .map(toProductCardData);
  };

  try {
    if (categorySlug) {
      const inStock = await fetchList({
        category: categorySlug,
        in_stock: true,
        per_page: 8,
        sort: "featured",
      });
      if (inStock.length > 0) {
        return inStock;
      }
      return await fetchList({
        category: categorySlug,
        per_page: 8,
        sort: "featured",
      });
    }
    return [];
  } catch {
    return [];
  }
}

export async function loadBrandForProduct(
  slug: string | null | undefined,
  locale: CatalogLocale,
): Promise<PublicBrandDetail | null> {
  if (!slug) {
    return null;
  }
  try {
    const response = await getPublicBrand(slug, { locale, ...revalidate });
    return response.data;
  } catch {
    return null;
  }
}

export async function loadPublicBrands(locale: CatalogLocale) {
  return getPublicBrands({ per_page: 48 }, { locale, ...revalidate });
}

export function defaultCatalogQuery(): CatalogQuery {
  return emptyCatalogQuery();
}
