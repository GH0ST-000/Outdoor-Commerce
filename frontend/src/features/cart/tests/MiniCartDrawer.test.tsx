import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { MiniCartDrawer } from "@/features/cart/components/MiniCartDrawer";
import { TestProviders } from "@/test/providers";
import { emptyCart } from "@/features/cart/lib/empty-cart";
import {
  replaceCart,
  resetCartStoreForTests,
  setDrawerOpen,
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

describe("MiniCartDrawer", () => {
  beforeEach(() => {
    resetCartStoreForTests();
  });

  it("opens with the cart title and restores focus on close", async () => {
    const user = userEvent.setup();
    const triggerRef = { current: document.createElement("button") };
    document.body.appendChild(triggerRef.current);
    replaceCart(cart);
    setDrawerOpen(true);

    render(
      <TestProviders>
        <MiniCartDrawer triggerRef={triggerRef} />
      </TestProviders>,
    );

    expect(
      screen.getByRole("dialog", { name: "Your cart" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Alpine Scope")).toBeInTheDocument();
    await user.keyboard("{Escape}");
    await waitFor(() => expect(triggerRef.current).toHaveFocus());
  });

  it("renders an empty state", () => {
    const triggerRef = { current: document.createElement("button") };
    replaceCart(emptyCart());
    setDrawerOpen(true);

    render(
      <TestProviders>
        <MiniCartDrawer triggerRef={triggerRef} />
      </TestProviders>,
    );

    expect(screen.getByText("Your cart is empty")).toBeInTheDocument();
  });
});
