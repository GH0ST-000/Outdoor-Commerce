import type { PublicProductDetail } from "@/features/catalog/types/public-catalog";
import type { ProductPurchaseIntent } from "@/features/product-detail/types";

export const PRODUCT_QUANTITY_UI_MAX = 12;

export function clampPurchaseQuantity(value: number): number {
  if (!Number.isInteger(value) || value < 1) {
    return 1;
  }
  return Math.min(PRODUCT_QUANTITY_UI_MAX, value);
}

export function buildPurchaseIntent(
  detail: Pick<PublicProductDetail, "id">,
  variantId: number,
  quantity: number,
  pricingSignature: string | null,
): ProductPurchaseIntent {
  return {
    product_id: detail.id,
    product_variant_id: variantId,
    quantity: clampPurchaseQuantity(quantity),
    pricing_signature: pricingSignature,
  };
}
