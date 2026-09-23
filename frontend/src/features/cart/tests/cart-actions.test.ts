import { beforeEach, describe, expect, it, vi } from "vitest";
import { emptyCart } from "@/features/cart/lib/empty-cart";
import {
  getCartSnapshot,
  replaceCart,
  resetCartStoreForTests,
} from "@/features/cart/state/cart-store";
import {
  addItemToCart,
  updateItemQuantity,
} from "@/features/cart/state/cart-actions";
import { ApiClientError } from "@/lib/api-client";
import type { PublicCart } from "@/features/cart/types";

const { addCartItem, updateCartItem } = vi.hoisted(() => ({
  addCartItem: vi.fn(),
  updateCartItem: vi.fn(),
}));

vi.mock("@/features/cart/api/cart-client", async () => {
  const actual = await vi.importActual<
    typeof import("@/features/cart/api/cart-client")
  >("@/features/cart/api/cart-client");

  return {
    ...actual,
    addCartItem: (...args: unknown[]) => addCartItem(...args),
    updateCartItem: (...args: unknown[]) => updateCartItem(...args),
  };
});

const nextCart: PublicCart = {
  ...emptyCart(),
  id: "cart-1",
  version: 2,
  item_count: 1,
  unique_item_count: 1,
  items: [
    {
      id: "line-1",
      quantity: 1,
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
        compare_at_price_minor: 10000,
        line_subtotal_minor: 10000,
        line_discount_minor: 0,
        line_total_minor: 10000,
        currency: "GEL",
        price_changed: false,
      },
      availability: {
        is_available: true,
        can_increment: true,
        can_decrement: false,
        maximum_allowed_quantity: 5,
        requested_quantity: 1,
      },
      issues: [],
    },
  ],
  totals: {
    items_subtotal_minor: 10000,
    discount_total_minor: 0,
    cart_total_minor: 10000,
    currency: "GEL",
  },
};

describe("cart actions", () => {
  beforeEach(() => {
    resetCartStoreForTests();
    addCartItem.mockReset();
    updateCartItem.mockReset();
  });

  it("replaces local state with the canonical add response", async () => {
    addCartItem.mockResolvedValue(nextCart);
    const result = await addItemToCart({ variant_id: 9, quantity: 1 });
    expect(result.item_count).toBe(1);
    expect(getCartSnapshot().cart.version).toBe(2);
    expect(getCartSnapshot().drawerOpen).toBe(true);
    expect(getCartSnapshot().announcement).toBe("added");
    expect(addCartItem).toHaveBeenCalledTimes(1);
    expect(addCartItem.mock.calls[0]?.[1]).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
  });

  it("ignores a second add while the first is pending", async () => {
    let resolveAdd: (cart: PublicCart) => void = () => undefined;
    addCartItem.mockImplementation(
      () =>
        new Promise<PublicCart>((resolve) => {
          resolveAdd = resolve;
        }),
    );

    const first = addItemToCart({ variant_id: 9, quantity: 1 });
    const second = await addItemToCart({ variant_id: 9, quantity: 1 });
    expect(second.item_count).toBe(0);
    expect(addCartItem).toHaveBeenCalledTimes(1);
    resolveAdd(nextCart);
    await first;
  });

  it("recovers from a version conflict without retrying", async () => {
    replaceCart({ ...nextCart, version: 1 });
    updateCartItem.mockRejectedValue(
      new ApiClientError({
        status: 409,
        code: "CART_VERSION_CONFLICT",
        message: "stale",
        conflictCart: { ...nextCart, version: 4, item_count: 2 },
      }),
    );

    await expect(updateItemQuantity("line-1", 2)).rejects.toBeInstanceOf(
      ApiClientError,
    );
    expect(getCartSnapshot().cart.version).toBe(4);
    expect(getCartSnapshot().announcement).toBe("conflict");
    expect(updateCartItem).toHaveBeenCalledTimes(1);
  });
});
