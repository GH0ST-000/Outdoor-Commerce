import type {
  AttributeStatus,
  AttributeType,
} from "@/features/catalog/attributes/types/attribute-types";

export type ProductVariantStatus = "draft" | "active" | "archived";

export type VariantAxis = {
  attribute_id: number;
  code: string;
  name: string | null;
  type: AttributeType;
  status: AttributeStatus;
  sort_order: number;
};

export type ProductVariantAxes = {
  product_id: number;
  axes: VariantAxis[];
};

export type VariantAttributeValue = {
  attribute_id: number;
  attribute_code: string | null;
  attribute_name: string | null;
  attribute_value_id: number;
  attribute_value_code: string | null;
  attribute_value_name: string | null;
  color_hex: string | null;
};

export type ProductVariantListItem = {
  id: number;
  product_id: number;
  sku: string;
  barcode: string | null;
  status: ProductVariantStatus;
  is_default: boolean;
  sort_order: number;
  combination_signature: string;
  attribute_values: VariantAttributeValue[];
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type VariantReadiness = {
  ready: boolean;
  issues: Record<string, string[]>;
  issue_count: number;
};

export type ProductVariantDetail = ProductVariantListItem & {
  combination_hash: string;
  readiness: VariantReadiness;
  created_by: number | null;
  updated_by: number | null;
};

export type VariantAxisSelection = {
  attribute_id: number;
  attribute_value_ids: number[];
};

export type VariantGenerationPreview = {
  axes: {
    attribute_id: number;
    code: string;
    name: string | null;
    value_ids: number[];
  }[];
  limit: number;
  total_combinations: number;
  existing_count: number;
  new_count: number;
  exceeds_limit: boolean;
  truncated: boolean;
  combinations: {
    signature: string;
    hash: string;
    exists: boolean;
    pairs: { attribute_id: number; attribute_value_id: number }[];
  }[];
};

export type VariantGenerationSummary = {
  requested: number;
  created: number;
  skipped: number;
  limit: number;
};

export type VariantGenerationResult = {
  created: ProductVariantListItem[];
  summary: VariantGenerationSummary | null;
};

export type ProductVariantListParams = {
  search?: string;
  status?: ProductVariantStatus | "";
  is_default?: "1" | "0" | "";
  include_deleted?: "1" | "0" | "";
  sort?: "created_at" | "updated_at" | "sort_order" | "sku" | "status";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type ProductVariantWritePayload = {
  sku?: string;
  barcode?: string | null;
  status?: ProductVariantStatus;
  sort_order?: number;
  is_default?: boolean;
  attribute_values?: {
    attribute_id: number;
    attribute_value_id: number;
  }[];
};
