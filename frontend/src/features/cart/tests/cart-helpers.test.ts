import { describe, expect, it } from "vitest";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import { canDirectAddToCart } from "@/features/cart/lib/direct-add";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";

describe("cart helpers", () => {
  it("generates unique idempotency keys", () => {
    const keys = new Set(
      Array.from({ length: 20 }, () => createCartIdempotencyKey()),
    );
    expect(keys.size).toBe(20);
    for (const key of keys) {
      expect(key).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
      );
    }
  });

  it("only allows direct add when a single default variant is purchasable", () => {
    const base: ProductCardData = {
      id: "1",
      slug: "scope",
      brand: "Ridge",
      name: { en: "Scope", ka: "Scope" },
      href: "/products/scope",
      imageSrc: "/placeholder.svg",
      imageAlt: { en: "Scope", ka: "Scope" },
      defaultVariantId: 9,
      variantCount: 1,
      purchasable: true,
      pricing: { currency: "GEL", is_range: false, amount_minor: 1000 },
    };

    expect(canDirectAddToCart(base)).toBe(true);
    expect(canDirectAddToCart({ ...base, variantCount: 2 })).toBe(false);
    expect(canDirectAddToCart({ ...base, defaultVariantId: null })).toBe(false);
    expect(
      canDirectAddToCart({
        ...base,
        pricing: { currency: "GEL", is_range: true },
      }),
    ).toBe(false);
  });
});
