import type { PublicMedia } from "@/features/catalog/types/public-catalog";

export type CartStatus =
  "active" | "merged" | "converted" | "expired" | "abandoned";

export type CartIssueCode =
  | "PRODUCT_UNAVAILABLE"
  | "VARIANT_UNAVAILABLE"
  | "INSUFFICIENT_STOCK"
  | "QUANTITY_LIMIT"
  | "QUANTITY_ADJUSTED_ON_MERGE"
  | "PRICE_CHANGED"
  | "PRICE_INCREASED"
  | "PRICE_DECREASED"
  | "PROMOTION_ENDED"
  | "PROMOTION_APPLIED"
  | "CART_EXPIRED";

export type CartIssue = {
  code: CartIssueCode | string;
  message: string;
  item_id?: string;
  context?: Record<string, unknown>;
};

export type CartLinePricing = {
  unit_price_minor: number;
  compare_at_price_minor: number;
  line_subtotal_minor: number;
  line_discount_minor: number;
  line_total_minor: number;
  currency: string;
  price_changed: boolean;
  pricing_signature?: string | null;
};

export type CartLineAvailability = {
  is_available: boolean;
  can_increment: boolean;
  can_decrement: boolean;
  maximum_allowed_quantity: number;
  requested_quantity: number;
};

export type CartLineProduct = {
  id: number;
  slug: string | null;
  name: string;
  href: string | null;
  brand: { name: string | null } | null;
  primary_media: PublicMedia | null;
};

export type CartLineVariant = {
  id: number;
  sku: string;
  label: string;
  attributes: Array<{
    code: string;
    name: string;
    value: { code: string; name: string; color_hex: string | null };
  }>;
};

export type CartLine = {
  id: string;
  quantity: number;
  product: CartLineProduct;
  variant: CartLineVariant;
  pricing: CartLinePricing;
  availability: CartLineAvailability;
  issues: CartIssue[];
};

export type CartTotals = {
  items_subtotal_minor: number;
  discount_total_minor: number;
  cart_total_minor: number;
  currency: string;
};

export type PublicCart = {
  id: string | null;
  status: CartStatus;
  version: number;
  currency: string;
  item_count: number;
  unique_item_count: number;
  items: CartLine[];
  totals: CartTotals;
  issues: CartIssue[];
  updated_at: string | null;
};

export type CartEnvelope = {
  data: PublicCart;
};

export type AddCartItemPayload = {
  variant_id: number;
  quantity: number;
  product_id?: number;
  cart_version?: number;
};

export type UpdateCartItemPayload = {
  quantity: number;
  cart_version?: number;
};
