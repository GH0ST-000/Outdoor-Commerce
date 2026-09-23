import type { PublicCart } from "@/features/cart/types";

export function emptyCart(currency = "GEL"): PublicCart {
  return {
    id: null,
    status: "active",
    version: 0,
    currency,
    item_count: 0,
    unique_item_count: 0,
    items: [],
    totals: {
      items_subtotal_minor: 0,
      discount_total_minor: 0,
      cart_total_minor: 0,
      currency,
    },
    issues: [],
    updated_at: null,
  };
}

export function isPublicCart(value: unknown): value is PublicCart {
  if (value === null || typeof value !== "object") {
    return false;
  }

  const record = value as Record<string, unknown>;
  return (
    Array.isArray(record.items) &&
    typeof record.version === "number" &&
    typeof record.item_count === "number" &&
    record.totals !== null &&
    typeof record.totals === "object"
  );
}
