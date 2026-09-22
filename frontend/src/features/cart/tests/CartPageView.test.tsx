import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { CartPageView } from "@/features/cart/components/CartPageView";
import { TestProviders } from "@/test/providers";
import { emptyCart } from "@/features/cart/lib/empty-cart";
import {
  replaceCart,
  resetCartStoreForTests,
} from "@/features/cart/state/cart-store";
import type { PublicCart } from "@/features/cart/types";

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const cart: PublicCart = {
  ...emptyCart(),
  id: "cart-1",
  version: 3,
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
        compare_at_price_minor: 12000,
        line_subtotal_minor: 12000,
        line_discount_minor: 2000,
        line_total_minor: 10000,
        currency: "GEL",
        price_changed: true,
      },
      availability: {
        is_available: false,
        can_increment: false,
        can_decrement: false,
        maximum_allowed_quantity: 0,
        requested_quantity: 1,
      },
      issues: [
        {
          code: "PRICE_CHANGED",
          message:
            "The current price is different from when this item was added.",
        },
        {
          code: "PRODUCT_UNAVAILABLE",
          message: "This product is no longer available.",
        },
      ],
    },
  ],
  totals: {
    items_subtotal_minor: 12000,
    discount_total_minor: 2000,
    cart_total_minor: 10000,
    currency: "GEL",
  },
};

describe("CartPageView", () => {
  beforeEach(() => {
    resetCartStoreForTests();
  });

  it("renders empty cart actions", () => {
    replaceCart(emptyCart());
    render(
      <TestProviders>
        <CartPageView />
      </TestProviders>,
    );
    expect(
      screen.getByRole("heading", { name: "Your cart" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Browse catalog" }),
    ).toBeInTheDocument();
  });

  it("renders line issues and the checkout notice", async () => {
    replaceCart(cart);
    const { container } = render(
      <TestProviders>
        <CartPageView />
      </TestProviders>,
    );
    expect(screen.getByText("Alpine Scope")).toBeInTheDocument();
    expect(
      screen.getByText(
        "The current price is different from when this item was added.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Shipping and final availability are calculated during checkout.",
      ),
    ).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });
});
