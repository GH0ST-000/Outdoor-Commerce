export type ProductStatus = "draft" | "active" | "archived";

export type ProductTranslation = {
  locale: string;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  seo_title: string | null;
  seo_description: string | null;
};

export type ProductReadinessSummary = {
  ready: boolean;
  issue_count: number;
};

export type ProductReadiness = {
  ready: boolean;
  issues: Record<string, string[]>;
  issue_count: number;
};

export type CatalogRef = {
  id: number;
  name: string | null;
  status?: string;
  sort_order?: number;
  parent_id?: number | null;
};

export type ProductListItem = {
  id: number;
  name: string | null;
  slug: string | null;
  status: ProductStatus;
  brand: Pick<CatalogRef, "id" | "name"> | null;
  primary_category: Pick<CatalogRef, "id" | "name"> | null;
  category_count: number;
  model_number: string | null;
  is_featured: boolean;
  readiness: ProductReadinessSummary;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type ProductDetail = {
  id: number;
  status: ProductStatus;
  brand_id: number | null;
  brand: CatalogRef | null;
  primary_category_id: number | null;
  primary_category: CatalogRef | null;
  categories: CatalogRef[];
  model_number: string | null;
  manufacturer_part_number: string | null;
  is_featured: boolean;
  sort_order: number;
  published_at: string | null;
  translations: ProductTranslation[];
  readiness: ProductReadiness;
  created_by: number | null;
  updated_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type CatalogOption = {
  id: number;
  name: string | null;
  status: string;
  parent_id?: number | null;
};

export type ProductListParams = {
  search?: string;
  status?: ProductStatus | "";
  brand_id?: number | string | "";
  category_id?: number | string | "";
  primary_category_id?: number | string | "";
  is_featured?: "1" | "0" | "";
  locale?: string;
  include_deleted?: "1" | "0" | "";
  sort?: "created_at" | "updated_at" | "published_at" | "sort_order" | "status";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type ProductTranslationInput = {
  locale: string;
  name: string;
  slug: string;
  short_description?: string | null;
  description?: string | null;
  seo_title?: string | null;
  seo_description?: string | null;
};

export type ProductWritePayload = {
  brand_id?: number | null;
  primary_category_id?: number | null;
  category_ids?: number[];
  status?: ProductStatus;
  model_number?: string | null;
  manufacturer_part_number?: string | null;
  is_featured?: boolean;
  sort_order?: number;
  translations?: ProductTranslationInput[];
  sync_translations?: boolean;
  remove_english?: boolean;
};
