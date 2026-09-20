import type { PaginatedResponse } from "@/features/admin/types/admin-types";

export type PriceListStatus = "draft" | "active" | "archived";
export type PricePeriodStatus = "draft" | "published" | "cancelled";
export type PromotionStatus = "draft" | "active" | "paused" | "archived";
export type DiscountType = "percentage" | "fixed_amount";
export type StackingMode = "exclusive" | "combinable";
export type PromotionTargetType =
  | "all_products"
  | "product"
  | "product_variant"
  | "category"
  | "brand";
export type PromotionTargetMode = "include" | "exclude";
export type PromotionLifecycle =
  | "draft"
  | "scheduled"
  | "live"
  | "paused"
  | "expired"
  | "archived";

export type PriceList = {
  id: number;
  code: string;
  name: string;
  currency_code: string;
  status: PriceListStatus;
  is_default: boolean;
  priority: number;
  prices_include_tax: boolean;
  priced_variant_count?: number;
  deleted_at?: string | null;
  created_at: string;
  updated_at: string;
};

export type PricePeriod = {
  id: number;
  variant_price_id: number;
  amount_minor: number;
  currency_code?: string;
  status: PricePeriodStatus;
  starts_at: string;
  ends_at: string | null;
  published_at: string | null;
  cancelled_at: string | null;
  lifecycle?: string;
};

export type VariantPriceRow = {
  id: number;
  price_list_id: number;
  product_variant_id: number;
  version: number;
  product_id?: number;
  product_name?: string;
  sku?: string | null;
  barcode?: string | null;
  variant_label?: string | null;
  currency_code: string;
  current_period?: PricePeriod | null;
  scheduled_period?: PricePeriod | null;
  effective_amount_minor?: number | null;
  pricing_ready?: boolean;
};

export type ProductPriceRange = {
  currency: string;
  min_amount_minor: number | null;
  max_amount_minor: number | null;
  is_range: boolean;
  priced_variant_count: number;
  unpriced_variant_count: number;
  unavailable?: boolean;
};

export type AppliedPromotion = {
  promotion_id: number;
  code: string;
  discount_type: DiscountType;
  discount_amount_minor: number;
  priority: number;
  stacking_mode: StackingMode;
};

export type PriceQuote = {
  variant_id: number;
  price_list_id: number;
  currency: string;
  base_amount_minor: number;
  final_amount_minor: number;
  discount_amount_minor: number;
  applied_promotions: AppliedPromotion[];
  calculated_at: string;
  price_period_id: number | null;
  pricing_signature: string;
};

export type PromotionTarget = {
  id?: number;
  target_type: PromotionTargetType;
  target_id: number | null;
  mode: PromotionTargetMode;
  label?: string;
};

export type Promotion = {
  id: number;
  code: string;
  name: string;
  description: string | null;
  status: PromotionStatus;
  lifecycle?: PromotionLifecycle;
  discount_type: DiscountType;
  percentage_basis_points: number | null;
  fixed_amount_minor: number | null;
  currency_code: string | null;
  priority: number;
  stacking_mode: StackingMode;
  starts_at: string;
  ends_at: string | null;
  maximum_discount_minor: number | null;
  targets?: PromotionTarget[];
  created_at: string;
  updated_at: string;
};

export type PromotionPreviewResult = {
  samples: Array<{
    variant_id: number;
    eligible: boolean;
    reason?: string | null;
    base_amount_minor?: number | null;
    final_amount_minor?: number | null;
    discount_amount_minor?: number | null;
    quote?: PriceQuote | null;
  }>;
  warnings: Array<{ code: string; message: string }>;
  conflicts: Array<{ code: string; message: string }>;
};

export type PriceListListParams = {
  search?: string;
  status?: PriceListStatus | "";
  currency_code?: string;
  is_default?: boolean | "1" | "0" | "";
  include_deleted?: boolean | "1" | "0" | "";
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type PriceListWritePayload = {
  code: string;
  name: string;
  currency_code: string;
  status: PriceListStatus;
  is_default: boolean;
  priority: number;
  prices_include_tax: boolean;
};

export type PaginatedPriceLists = PaginatedResponse<PriceList>;

export type VariantPriceListParams = {
  search?: string;
  price_list_id?: number | string;
  product_id?: number | string;
  product_variant_id?: number | string;
  pricing_ready?: boolean | "1" | "0" | "";
  period_status?: PricePeriodStatus | "";
  locale?: string;
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type PaginatedVariantPrices = PaginatedResponse<VariantPriceRow>;

export type VariantPriceDetail = VariantPriceRow & {
  periods?: PricePeriod[];
};

export type CreatePricePeriodPayload = {
  amount_minor: number;
  starts_at: string;
  ends_at?: string | null;
};

export type ReplacePricePayload = {
  amount_minor: number;
  starts_at: string;
  ends_at?: string | null;
  expected_version: number;
  confirm_replace?: boolean;
};

export type UpdatePricePeriodPayload = {
  amount_minor?: number;
  starts_at?: string;
  ends_at?: string | null;
};

export type BulkPriceEntry = {
  price_list_id: number;
  product_variant_id: number;
  amount_minor: number;
  starts_at?: string;
  expected_version?: number;
};

export type BulkPricePayload = {
  items: BulkPriceEntry[];
  publish?: boolean;
};

export type PromotionListParams = {
  search?: string;
  status?: PromotionStatus | "";
  discount_type?: DiscountType | "";
  lifecycle?: PromotionLifecycle | "";
  include_deleted?: boolean | "1" | "0" | "";
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type PaginatedPromotions = PaginatedResponse<Promotion>;

export type PromotionWritePayload = {
  code: string;
  name: string;
  description?: string | null;
  discount_type: DiscountType;
  percentage_basis_points?: number | null;
  fixed_amount_minor?: number | null;
  currency_code?: string | null;
  priority: number;
  stacking_mode: StackingMode;
  starts_at: string;
  ends_at?: string | null;
  maximum_discount_minor?: number | null;
};

export type PromotionTargetsPayload = {
  targets: PromotionTarget[];
};

export type PromotionPreviewPayload = {
  price_list_id?: number;
  variant_ids?: number[];
  promotion?: Partial<PromotionWritePayload> & { targets?: PromotionTarget[] };
};
