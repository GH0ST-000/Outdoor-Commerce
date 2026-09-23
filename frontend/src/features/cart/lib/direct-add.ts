import type { ProductCardData } from "@/features/storefront/types/storefront-types";

export function canDirectAddToCart(product: ProductCardData): boolean {
  return (
    product.variantCount === 1 &&
    product.defaultVariantId != null &&
    product.purchasable === true &&
    product.pricing?.is_range !== true
  );
}
