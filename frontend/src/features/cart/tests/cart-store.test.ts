import { beforeEach, describe, expect, it } from "vitest";
import { emptyCart } from "@/features/cart/lib/empty-cart";
import {
  getCartSnapshot,
  replaceCart,
  resetCartStoreForTests,
  setDrawerOpen,
} from "@/features/cart/state/cart-store";
import { cartFromConflict } from "@/features/cart/api/cart-client";
import { ApiClientError } from "@/lib/api-client";
import type { PublicCart } from "@/features/cart/types";

const cart: PublicCart = {
  ...emptyCart(),
  id: "cart-1",
  version: 4,
  item_count: 2,
  unique_item_count: 1,
  items: [
    {
      id: "line-1",
      quantity: 2,
      product: {
        id: 1,
        slug: "scope",
        name: "Alpine Scope",
        href: "/products/scope",
        brand: { name: "Ridge" },
        primary_media: null,
      },
      variant: { id: 9, sku: "SKU-1", label: "Black", attributes: [] },
      pricing: {
        unit_price_minor: 10000,
        compare_at_price_minor: 12000,
        line_subtotal_minor: 24000,
        line_discount_minor: 4000,
        line_total_minor: 20000,
        currency: "GEL",
        price_changed: false,
      },
      availability: {
        is_available: true,
        can_increment: true,
        can_decrement: true,
        maximum_allowed_quantity: 5,
        requested_quantity: 2,
      },
      issues: [],
    },
  ],
  totals: {
    items_subtotal_minor: 24000,
    discount_total_minor: 4000,
    cart_total_minor: 20000,
    currency: "GEL",
  },
};

describe("cart store", () => {
  beforeEach(() => {
    resetCartStoreForTests();
  });

  it("replaces local state with the canonical server cart", () => {
    replaceCart(cart);
    expect(getCartSnapshot().cart.item_count).toBe(2);
    expect(getCartSnapshot().cart.totals.cart_total_minor).toBe(20000);
    expect(getCartSnapshot().status).toBe("ready");
  });

  it("opens and closes the mini-cart", () => {
    setDrawerOpen(true);
    expect(getCartSnapshot().drawerOpen).toBe(true);
    setDrawerOpen(false);
    expect(getCartSnapshot().drawerOpen).toBe(false);
  });

  it("recovers a cart from a version conflict error", () => {
    const error = new ApiClientError({
      status: 409,
      code: "CART_VERSION_CONFLICT",
      message: "stale",
      conflictCart: cart,
    });
    expect(cartFromConflict(error)?.version).toBe(4);
  });
});
