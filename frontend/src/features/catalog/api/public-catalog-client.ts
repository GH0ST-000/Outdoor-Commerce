import { ApiClientError } from "@/lib/api-client";
import { getApiBaseUrl, resolveServerApiBaseUrls } from "@/lib/env";
import type {
  CatalogLocale,
  PublicBrandDetail,
  PublicBrandSummary,
  PublicCatalogEnvelope,
  PublicCategoryDetail,
  PublicCategorySummary,
  PublicFacets,
  PublicProductCard,
  PublicProductDetail,
  PublicProductListParams,
  PublicGroupedSearchData,
  PublicSearchSuggestionsData,
} from "@/features/catalog/types/public-catalog";

export type CatalogRequestOptions = {
  locale?: CatalogLocale;
  signal?: AbortSignal;
  cache?: RequestCache;
  next?: { revalidate?: number; tags?: string[] };
};

function catalogBaseUrls(): string[] {
  if (typeof window === "undefined") {
    return resolveServerApiBaseUrls();
  }

  return [getApiBaseUrl()];
}

function isAbortError(error: unknown): boolean {
  return (
    (error instanceof DOMException && error.name === "AbortError") ||
    (error instanceof Error && error.name === "AbortError")
  );
}

function isUnreachableHost(error: unknown): boolean {
  if (isAbortError(error)) {
    return false;
  }
  if (!(error instanceof Error)) {
    return false;
  }
  const cause = (
    error as Error & { cause?: { code?: string; message?: string } }
  ).cause;
  const code = cause?.code ?? "";
  const details = `${error.message} ${cause?.message ?? ""} ${code}`;

  return (
    code === "ENOTFOUND" ||
    code === "ECONNREFUSED" ||
    code === "EAI_AGAIN" ||
    code === "UND_ERR_CONNECT_TIMEOUT" ||
    details.includes("ENOTFOUND") ||
    error.message === "fetch failed"
  );
}

async function catalogFetch(
  path: string,
  init: RequestInit,
): Promise<Response> {
  const bases = catalogBaseUrls();

  for (const [index, base] of bases.entries()) {
    const hasFallback = index < bases.length - 1;
    try {
      const response = await fetch(`${base}${path}`, init);
      if (!response.ok && response.status >= 500 && hasFallback) {
        continue;
      }
      return response;
    } catch (error) {
      if (isAbortError(error)) {
        throw error;
      }
      if (hasFallback && isUnreachableHost(error)) {
        continue;
      }
      break;
    }
  }

  throw new ApiClientError({
    status: 503,
    code: "CATALOG_UNAVAILABLE",
    message: "The catalog could not be loaded.",
  });
}

function serializeParams(params: PublicProductListParams): string {
  const search = new URLSearchParams();
  const entries: Array<[string, string | number | boolean | undefined]> = [
    ["locale", params.locale],
    ["currency", params.currency],
    ["category", params.category],
    [
      "include_descendants",
      params.include_descendants === undefined
        ? undefined
        : params.include_descendants
          ? "1"
          : "0",
    ],
    ["min_price", params.min_price],
    ["max_price", params.max_price],
    [
      "in_stock",
      params.in_stock === undefined ? undefined : params.in_stock ? "1" : "0",
    ],
    [
      "on_sale",
      params.on_sale === undefined ? undefined : params.on_sale ? "1" : "0",
    ],
    [
      "featured",
      params.featured === undefined ? undefined : params.featured ? "1" : "0",
    ],
    ["q", params.q],
    ["sort", params.sort],
    ["page", params.page],
    ["per_page", params.per_page],
  ];

  for (const [key, value] of entries) {
    if (value === undefined || value === "") {
      continue;
    }
    search.append(key, String(value));
  }

  for (const brand of params.brand ?? []) {
    search.append("brand[]", brand);
  }

  const attributes = params.attribute ?? {};
  const codes = Object.keys(attributes).sort();
  for (const code of codes) {
    for (const value of attributes[code] ?? []) {
      search.append(`attribute[${code}][]`, value);
    }
  }

  const query = search.toString();
  return query === "" ? "" : `?${query}`;
}

export function buildProductListPath(
  params: PublicProductListParams = {},
): string {
  return `/v1/catalog/products${serializeParams(params)}`;
}

export function buildProductFacetsPath(
  params: PublicProductListParams = {},
): string {
  return `/v1/catalog/products/facets${serializeParams(params)}`;
}

async function catalogRequest<T>(
  path: string,
  options: CatalogRequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  if (options.locale) {
    headers["X-Locale"] = options.locale;
    headers["Accept-Language"] = options.locale;
  }

  const init: RequestInit & {
    next?: { revalidate?: number; tags?: string[] };
  } = {
    method: "GET",
    headers,
    signal: options.signal,
    cache: options.cache,
  };

  if (options.next) {
    init.next = options.next;
  }

  const response = await catalogFetch(path, init);

  if (!response.ok) {
    type CatalogErrorPayload = {
      error?: {
        code?: string;
        message?: string;
        details?: Record<string, string[]>;
      };
      meta?: { request_id?: string };
    };
    let payload: CatalogErrorPayload | null = null;
    try {
      payload = (await response.json()) as CatalogErrorPayload;
    } catch {
      payload = null;
    }

    throw new ApiClientError({
      status: response.status,
      code:
        payload?.error?.code ??
        (response.status === 404
          ? "CATALOG_PRODUCT_NOT_FOUND"
          : response.status === 429
            ? "CATALOG_RATE_LIMITED"
            : "SERVER_ERROR"),
      message:
        payload?.error?.message ??
        (response.status === 429
          ? "Too many attempts. Please wait and try again."
          : "Something went wrong. Please try again."),
      details: payload?.error?.details,
      requestId:
        payload?.meta?.request_id ??
        response.headers.get("X-Request-ID") ??
        undefined,
    });
  }

  return (await response.json()) as T;
}

export async function getPublicCategories(
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicCategorySummary[]>> {
  const locale = options.locale ? `?locale=${options.locale}` : "";
  return catalogRequest(`/v1/catalog/categories${locale}`, options);
}

export async function getPublicCategory(
  slug: string,
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicCategoryDetail>> {
  const locale = options.locale ? `?locale=${options.locale}` : "";
  return catalogRequest(
    `/v1/catalog/categories/${encodeURIComponent(slug)}${locale}`,
    options,
  );
}

export async function getPublicBrands(
  params: { page?: number; per_page?: number } = {},
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicBrandSummary[]>> {
  const search = new URLSearchParams();
  if (options.locale) search.set("locale", options.locale);
  if (params.page) search.set("page", String(params.page));
  if (params.per_page) search.set("per_page", String(params.per_page));
  const query = search.toString();
  return catalogRequest(
    `/v1/catalog/brands${query ? `?${query}` : ""}`,
    options,
  );
}

export async function getPublicBrand(
  slug: string,
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicBrandDetail>> {
  const locale = options.locale ? `?locale=${options.locale}` : "";
  return catalogRequest(
    `/v1/catalog/brands/${encodeURIComponent(slug)}${locale}`,
    options,
  );
}

export async function getPublicProducts(
  params: PublicProductListParams = {},
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicProductCard[]>> {
  return catalogRequest(
    buildProductListPath({
      ...params,
      locale: options.locale ?? params.locale,
    }),
    options,
  );
}

export async function getPublicProductFacets(
  params: PublicProductListParams = {},
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicFacets>> {
  return catalogRequest(
    buildProductFacetsPath({
      ...params,
      locale: options.locale ?? params.locale,
    }),
    options,
  );
}

export async function getPublicProduct(
  slug: string,
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicProductDetail>> {
  const locale = options.locale ? `?locale=${options.locale}` : "";
  return catalogRequest(
    `/v1/catalog/products/${encodeURIComponent(slug)}${locale}`,
    options,
  );
}

export function buildGroupedSearchPath(params: {
  q: string;
  locale?: CatalogLocale;
  limit?: number;
}): string {
  const search = new URLSearchParams();
  search.set("q", params.q);
  if (params.locale) search.set("locale", params.locale);
  if (params.limit) search.set("limit", String(params.limit));
  return `/v1/search?${search.toString()}`;
}

export function buildSearchSuggestionsPath(params: {
  q: string;
  locale?: CatalogLocale;
  limit?: number;
}): string {
  const search = new URLSearchParams();
  search.set("q", params.q);
  if (params.locale) search.set("locale", params.locale);
  if (params.limit) search.set("limit", String(params.limit));
  return `/v1/search/suggestions?${search.toString()}`;
}

export async function getGroupedSearch(
  params: { q: string; locale?: CatalogLocale; limit?: number },
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicGroupedSearchData>> {
  return catalogRequest(buildGroupedSearchPath(params), {
    ...options,
    cache: "no-store",
  });
}

export async function getSearchSuggestions(
  params: { q: string; locale?: CatalogLocale; limit?: number },
  options: CatalogRequestOptions = {},
): Promise<PublicCatalogEnvelope<PublicSearchSuggestionsData>> {
  return catalogRequest(buildSearchSuggestionsPath(params), {
    ...options,
    cache: "no-store",
  });
}
