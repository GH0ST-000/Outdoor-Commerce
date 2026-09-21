import { describe, expect, it } from "vitest";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import {
  PRODUCT_QUANTITY_UI_MAX,
  buildPurchaseIntent,
  clampPurchaseQuantity,
} from "@/features/product-detail/cart/purchase-intent";

describe("purchase intent", () => {
  it("clamps quantity without using inventory as a maximum", () => {
    expect(clampPurchaseQuantity(0)).toBe(1);
    expect(clampPurchaseQuantity(PRODUCT_QUANTITY_UI_MAX + 8)).toBe(
      PRODUCT_QUANTITY_UI_MAX,
    );
  });

  it("builds a typed intent from public ids and the pricing signature", () => {
    expect(buildPurchaseIntent({ id: 12 }, 501, 2, "sig-a")).toEqual({
      product_id: 12,
      product_variant_id: 501,
      quantity: 2,
      pricing_signature: "sig-a",
    });
  });

  it("keeps cart disabled unless the public flag is explicitly true", () => {
    expect(isCartEnabled()).toBe(false);
  });
});
