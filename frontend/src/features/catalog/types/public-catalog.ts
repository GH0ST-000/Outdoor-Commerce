export type CatalogLocale = "ka" | "en";
export type CatalogCurrency = "GEL";

export type PublicAvailabilityStatus =
  "in_stock" | "low_stock" | "out_of_stock" | "unavailable";

export type PublicAvailability = {
  status: PublicAvailabilityStatus;
  purchasable: boolean;
  low_stock: boolean;
};

export type PublicAppliedPromotion = {
  code: string;
  name: string;
  discount_type: string;
};

export type PublicVariantPrice = {
  currency: string;
  base_amount_minor: number | null;
  final_amount_minor: number | null;
  discount_amount_minor: number | null;
  on_sale: boolean;
  applied_promotions: PublicAppliedPromotion[];
  calculated_at: string;
  signature: string | null;
};

export type PublicProductPriceRange = {
  currency: string;
  min_final_amount_minor: number | null;
  max_final_amount_minor: number | null;
  min_base_amount_minor: number | null;
  is_range: boolean;
  on_sale: boolean;
};

export type PublicMediaSources = Record<
  string,
  {
    srcset: string;
    presets: Record<
      string,
      { url: string; width: number; height: number; byte_size: number }
    >;
  }
>;

export type PublicMedia = {
  alt: string | null;
  caption: string | null;
  width: number | null;
  height: number | null;
  aspect_ratio: number | null;
  dominant_color: string | null;
  focal_point: { x: number; y: number } | null;
  sources: PublicMediaSources | null;
};

export type PublicSeo = {
  title: string;
  description: string | null;
  canonical_path: string;
  alternate_locale_paths: Record<string, string>;
  open_graph_media: PublicMedia | null;
  robots: string;
};

export type PublicBreadcrumb = {
  name: string;
  slug: string;
  path: string;
};

export type PublicCategorySummary = {
  id: number;
  name: string | null;
  slug: string;
  path?: string;
  sort_order?: number;
  children?: PublicCategorySummary[];
};

export type PublicCategoryDetail = PublicCategorySummary & {
  description: string | null;
  seo: PublicSeo;
  breadcrumbs: PublicBreadcrumb[];
  parent: PublicCategorySummary | null;
  children: PublicCategorySummary[];
  product_count: number;
  canonical_path: string;
  alternate_locale_paths: Record<string, string>;
  used_fallback: boolean;
};

export type PublicBrandSummary = {
  id: number;
  name: string | null;
  slug: string;
  path: string;
  is_featured: boolean;
  product_count: number;
  used_fallback: boolean;
};

export type PublicBrandDetail = PublicBrandSummary & {
  description: string | null;
  website_url: string | null;
  canonical_path: string;
  alternate_locale_paths: Record<string, string>;
  seo: PublicSeo;
};

export type PublicProductCard = {
  id: number;
  name: string | null;
  slug: string | null;
  href: string;
  brand: { id: number; name: string | null; slug: string | null } | null;
  primary_category: {
    id: number;
    name: string | null;
    slug: string | null;
  } | null;
  primary_media: PublicMedia | null;
  price: PublicProductPriceRange;
  availability: PublicAvailability;
  is_featured: boolean;
  variant_count: number;
  used_fallback: boolean;
};

export type PublicVariantAttribute = {
  code: string;
  name: string;
  value: {
    code: string;
    name: string;
    color_hex: string | null;
  };
};

export type PublicVariantCombination = {
  id: number;
  sku: string;
  is_default: boolean;
  combination_label: string;
  attributes: PublicVariantAttribute[];
  media: PublicMedia[];
  price: PublicVariantPrice;
  availability: PublicAvailability;
};

export type PublicVariantAxis = {
  id: number;
  code: string;
  name: string;
  type: "select" | "color";
  values: Array<{
    id: number;
    code: string;
    name: string;
    color_hex: string | null;
  }>;
};

export type PublicVariantMatrix = {
  axes: PublicVariantAxis[];
  combinations: PublicVariantCombination[];
  default_variant_id: number | null;
};

export type PublicProductDetail = {
  id: number;
  name: string | null;
  slug: string | null;
  short_description: string | null;
  description: string | null;
  model_number: string | null;
  is_featured: boolean;
  used_fallback: boolean;
  brand: {
    id: number;
    name: string | null;
    slug: string | null;
    path: string;
  } | null;
  primary_category: {
    id: number;
    name: string | null;
    slug: string | null;
    path: string;
  } | null;
  categories: Array<{ id: number; name: string | null; slug: string | null }>;
  breadcrumbs: PublicBreadcrumb[];
  gallery: PublicMedia[];
  price: PublicProductPriceRange;
  availability: PublicAvailability;
  variants: PublicVariantMatrix;
  default_variant_id: number | null;
  seo: PublicSeo;
  canonical_path: string;
  alternate_locale_paths: Record<string, string>;
  updated_at: string | null;
};

export type PublicFacetValue = {
  code: string;
  name: string | null;
  color_hex?: string | null;
  count: number;
};

export type PublicFacets = {
  brands: Array<{
    id: number;
    code: string | null;
    name: string | null;
    count: number;
  }>;
  categories: Array<{
    id: number;
    code: string | null;
    name: string | null;
    count: number;
  }>;
  attributes: Array<{
    code: string;
    name: string;
    values: PublicFacetValue[];
  }>;
  price_range: {
    currency: string;
    min_final_amount_minor: number | null;
    max_final_amount_minor: number | null;
  };
  in_stock_count: number;
  on_sale_count: number;
};

export type PublicPagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type CatalogSort =
  | "default"
  | "featured"
  | "newest"
  | "price_asc"
  | "price_desc"
  | "name_asc"
  | "name_desc";

export type PublicProductListParams = {
  locale?: CatalogLocale;
  currency?: CatalogCurrency;
  category?: string;
  include_descendants?: boolean;
  brand?: string[];
  attribute?: Record<string, string[]>;
  min_price?: number;
  max_price?: number;
  in_stock?: boolean;
  on_sale?: boolean;
  featured?: boolean;
  q?: string;
  sort?: CatalogSort;
  page?: number;
  per_page?: number;
};

export type PublicCatalogMeta = {
  pagination?: PublicPagination;
  filters?: Record<string, unknown>;
  sort?: string;
  locale?: string;
  currency?: string;
  request_id?: string;
  search_mode?: string;
  used_fallback?: boolean;
};

export type PublicCatalogEnvelope<T> = {
  data: T;
  meta?: PublicCatalogMeta;
  links?: { next: string | null; prev: string | null };
};
