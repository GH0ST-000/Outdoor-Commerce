import type {
  CatalogSort,
  PublicProductListParams,
} from "@/features/catalog/types/public-catalog";

export const CATALOG_SORTS: CatalogSort[] = [
  "default",
  "featured",
  "newest",
  "price_asc",
  "price_desc",
  "name_asc",
  "name_desc",
];

export type CatalogQuery = {
  brand: string[];
  attribute: Record<string, string[]>;
  min_price?: number;
  max_price?: number;
  in_stock?: boolean;
  on_sale?: boolean;
  featured?: boolean;
  q?: string;
  sort: CatalogSort;
  page: number;
};

export type SearchParamsInput = Record<string, string | string[] | undefined>;

const ATTRIBUTE_KEY = /^attribute\[([^\]]+)\](?:\[\])?$/;

function asList(value: string | string[] | undefined): string[] {
  if (value === undefined || value === "") {
    return [];
  }
  const items = Array.isArray(value) ? value : [value];
  return items
    .flatMap((item) => item.split(","))
    .map((item) => item.trim())
    .filter(Boolean);
}

function asInt(value: string | string[] | undefined): number | undefined {
  const raw = Array.isArray(value) ? value[0] : value;
  if (raw === undefined || raw === "") {
    return undefined;
  }
  if (!/^\d+$/.test(raw)) {
    return undefined;
  }
  const numeric = Number(raw);
  if (!Number.isSafeInteger(numeric) || numeric < 0) {
    return undefined;
  }
  return numeric;
}

function asFlag(value: string | string[] | undefined): boolean | undefined {
  const raw = Array.isArray(value) ? value[0] : value;
  if (raw === undefined || raw === "") {
    return undefined;
  }
  if (raw === "1" || raw === "true") {
    return true;
  }
  if (raw === "0" || raw === "false") {
    return false;
  }
  return undefined;
}

function asSort(
  value: string | string[] | undefined,
  fallback: CatalogSort = "featured",
): CatalogSort {
  const raw = Array.isArray(value) ? value[0] : value;
  if (raw && (CATALOG_SORTS as string[]).includes(raw)) {
    return raw as CatalogSort;
  }
  return fallback;
}

export function emptyCatalogQuery(): CatalogQuery {
  return {
    brand: [],
    attribute: {},
    sort: "featured",
    page: 1,
  };
}

export function parseCatalogSearchParams(
  params: SearchParamsInput,
  options: { defaultSort?: CatalogSort } = {},
): CatalogQuery {
  const brand = [...asList(params.brand), ...asList(params["brand[]"])].filter(
    (item, index, all) => all.indexOf(item) === index,
  );

  const attribute: Record<string, string[]> = {};
  for (const [key, value] of Object.entries(params)) {
    const match = ATTRIBUTE_KEY.exec(key);
    if (!match) {
      continue;
    }
    const code = match[1];
    if (!code) {
      continue;
    }
    const next = asList(value);
    if (next.length === 0) {
      continue;
    }
    attribute[code] = [...(attribute[code] ?? []), ...next].filter(
      (item, index, all) => all.indexOf(item) === index,
    );
  }

  const minPrice = asInt(params.min_price);
  const maxPrice = asInt(params.max_price);
  let min_price = minPrice;
  let max_price = maxPrice;
  if (
    min_price !== undefined &&
    max_price !== undefined &&
    min_price > max_price
  ) {
    min_price = maxPrice;
    max_price = minPrice;
  }

  const page = asInt(params.page) ?? 1;

  return {
    brand,
    attribute,
    min_price,
    max_price,
    in_stock: asFlag(params.in_stock) || undefined,
    on_sale: asFlag(params.on_sale) || undefined,
    featured: asFlag(params.featured) || undefined,
    q: asList(params.q)[0],
    sort: asSort(params.sort, options.defaultSort ?? "featured"),
    page: page < 1 ? 1 : page,
  };
}

export function catalogQueryHasFilters(query: CatalogQuery): boolean {
  return (
    query.brand.length > 0 ||
    Object.values(query.attribute).some((values) => values.length > 0) ||
    query.min_price !== undefined ||
    query.max_price !== undefined ||
    query.in_stock === true ||
    query.on_sale === true ||
    query.featured === true ||
    Boolean(query.q)
  );
}

export function serializeCatalogSearchParams(
  query: CatalogQuery,
  options: { omitDefaults?: boolean; defaultSort?: CatalogSort } = {},
): string {
  const search = new URLSearchParams();
  const omitDefaults = options.omitDefaults !== false;
  const defaultSort = options.defaultSort ?? "featured";

  const brands = [...query.brand].sort();
  for (const brand of brands) {
    search.append("brand", brand);
  }

  const codes = Object.keys(query.attribute).sort();
  for (const code of codes) {
    const values = [...(query.attribute[code] ?? [])].sort();
    for (const value of values) {
      search.append(`attribute[${code}]`, value);
    }
  }

  if (query.min_price !== undefined) {
    search.set("min_price", String(query.min_price));
  }
  if (query.max_price !== undefined) {
    search.set("max_price", String(query.max_price));
  }
  if (query.in_stock) {
    search.set("in_stock", "1");
  }
  if (query.on_sale) {
    search.set("on_sale", "1");
  }
  if (query.featured) {
    search.set("featured", "1");
  }
  if (query.q) {
    search.set("q", query.q);
  }
  if (!omitDefaults || query.sort !== defaultSort) {
    search.set("sort", query.sort);
  }
  if (!omitDefaults || query.page > 1) {
    search.set("page", String(query.page));
  }

  return search.toString();
}

export function catalogQueryToListParams(
  query: CatalogQuery,
  extras: Pick<PublicProductListParams, "category" | "locale" | "brand"> = {},
): PublicProductListParams {
  const attributes = Object.fromEntries(
    Object.entries(query.attribute).filter(([, values]) => values.length > 0),
  );

  return {
    ...extras,
    brand: query.brand.length > 0 ? query.brand : extras.brand,
    attribute: Object.keys(attributes).length > 0 ? attributes : undefined,
    min_price: query.min_price,
    max_price: query.max_price,
    in_stock: query.in_stock || undefined,
    on_sale: query.on_sale || undefined,
    featured: query.featured || undefined,
    q: query.q,
    sort: query.sort,
    page: query.page,
    per_page: 10,
  };
}

export function catalogHref(
  pathname: string,
  query: CatalogQuery,
  options: { defaultSort?: CatalogSort } = {},
): string {
  const serialized = serializeCatalogSearchParams(query, options);
  return serialized === "" ? pathname : `${pathname}?${serialized}`;
}

export function parseSearchPageParams(params: SearchParamsInput): CatalogQuery {
  return parseCatalogSearchParams(params, { defaultSort: "default" });
}

export function emptySearchQuery(q?: string): CatalogQuery {
  return {
    ...emptyCatalogQuery(),
    q,
    sort: "default",
  };
}
