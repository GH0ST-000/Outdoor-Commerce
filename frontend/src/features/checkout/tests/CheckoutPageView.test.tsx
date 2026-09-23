import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { CheckoutPageView } from "@/features/checkout/components/CheckoutPageView";
import { TestProviders } from "@/test/providers";
import { emptyCart } from "@/features/cart/lib/empty-cart";
import {
  replaceCart,
  resetCartStoreForTests,
} from "@/features/cart/state/cart-store";
import type { PublicCart } from "@/features/cart/types";
import type {
  CheckoutEnvelope,
  CheckoutQuote,
} from "@/features/checkout/types";
import {
  createCheckoutQuote,
  createCheckoutSession,
  updateCheckoutContact,
  updateCheckoutFulfillment,
} from "@/features/checkout/api/checkout-client";
import { createOrder } from "@/features/orders/api/order-client";

const quote: CheckoutQuote = {
  id: "22222222-2222-2222-2222-222222222222",
  revision: 1,
  status: "active",
  currency: "GEL",
  expires_at: new Date(Date.now() + 14 * 60 * 1000).toISOString(),
  remaining_seconds: 840,
  cart_version: 1,
  items: [
    {
      id: "ql-1",
      product_id: 1,
      variant_id: 9,
      slug: "scope",
      name: "Alpine Scope",
      variant_label: "Black",
      sku: "SKU-1",
      quantity: 1,
      media: null,
      pricing: {
        unit_base_price_minor: 10000,
        unit_effective_price_minor: 10000,
        line_subtotal_minor: 10000,
        line_discount_minor: 0,
        line_total_minor: 10000,
        currency: "GEL",
      },
      attributes: [],
      promotions: [],
      restriction: null,
      issues: [],
    },
  ],
  fulfillment: {
    method_code: "store_pickup",
    name: "Store pickup",
    amount_minor: 0,
    estimated_min_days: null,
    estimated_max_days: null,
  },
  totals: {
    items_subtotal_minor: 10000,
    discount_total_minor: 0,
    delivery_total_minor: 0,
    tax_total_minor: 0,
    grand_total_minor: 10000,
    currency: "GEL",
    price_includes_tax: true,
  },
  adjustments: [],
  restrictions: [],
  fingerprint: "public-fingerprint",
};

const session: CheckoutEnvelope = {
  data: {
    checkout_session: {
      id: "11111111-1111-1111-1111-111111111111",
      status: "draft",
      version: 0,
      currency: "GEL",
      cart_version: 1,
      expires_at: null,
      contact: {
        first_name: null,
        last_name: null,
        email: null,
        phone: null,
        customer_note: null,
        complete: false,
      },
      address: null,
      billing_same_as_shipping: true,
      fulfillment: { method_code: null, pickup_location_id: null },
      available_fulfillment_methods: [
        {
          id: "m1",
          code: "store_pickup",
          type: "store_pickup",
          name: "Store pickup",
          description: "Collect in Tbilisi",
          eligible: true,
          amount_minor: 0,
          currency: "GEL",
          estimated_min_days: null,
          estimated_max_days: null,
          unavailable_reason: null,
          requires_address: false,
          pickup_locations: [
            {
              id: "p1",
              name: "Tbilisi store",
              address: "Tbilisi",
              phone: null,
              working_hours: null,
              instructions: null,
              selected: true,
            },
          ],
        },
        {
          id: "m2",
          code: "local_delivery",
          type: "local_delivery",
          name: "Local delivery",
          description: "Tbilisi delivery",
          eligible: true,
          amount_minor: 500,
          currency: "GEL",
          estimated_min_days: null,
          estimated_max_days: null,
          unavailable_reason: null,
          requires_address: true,
        },
        {
          id: "m3",
          code: "courier_delivery",
          type: "courier_delivery",
          name: "Courier",
          description: "Not available here",
          eligible: false,
          amount_minor: null,
          currency: "GEL",
          estimated_min_days: null,
          estimated_max_days: null,
          unavailable_reason: "Not available for this address",
          requires_address: true,
        },
      ],
      quote: null,
    },
    quote: null,
  },
};

const cart: PublicCart = {
  ...emptyCart(),
  id: "cart-1",
  version: 1,
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

vi.mock("@/features/checkout/api/checkout-client", async () => {
  const actual = await vi.importActual<
    typeof import("@/features/checkout/api/checkout-client")
  >("@/features/checkout/api/checkout-client");
  return {
    ...actual,
    createCheckoutSession: vi.fn(async () => session.data.checkout_session),
    getCheckoutSession: vi.fn(async () => session.data.checkout_session),
    updateCheckoutContact: vi.fn(async () => ({
      ...session.data.checkout_session,
      version: 1,
      contact: {
        first_name: "Nino",
        last_name: "Beridze",
        email: "nino@example.com",
        phone: "+995555123456",
        customer_note: null,
        complete: true,
      },
      status: "ready",
    })),
    updateCheckoutAddress: vi.fn(async () => ({
      ...session.data.checkout_session,
      version: 2,
      contact: {
        first_name: "Nino",
        last_name: "Beridze",
        email: "nino@example.com",
        phone: "+995555123456",
        customer_note: null,
        complete: true,
      },
    })),
    updateCheckoutFulfillment: vi.fn(async () => ({
      ...session.data.checkout_session,
      version: 3,
      contact: {
        first_name: "Nino",
        last_name: "Beridze",
        email: "nino@example.com",
        phone: "+995555123456",
        customer_note: null,
        complete: true,
      },
      fulfillment: { method_code: "store_pickup", pickup_location_id: "p1" },
    })),
    createCheckoutQuote: vi.fn(async () => ({
      ...session.data.checkout_session,
      version: 4,
      status: "quoted",
      quote,
    })),
  };
});

vi.mock("@/features/orders/api/order-client", () => ({
  createOrder: vi.fn(),
  rememberOrderIdempotencyKey: vi.fn(() => "order-key-1"),
  clearOrderIdempotencyKey: vi.fn(),
}));

async function fillContact(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText(/First name/i), "Nino");
  await user.type(screen.getByLabelText(/Last name/i), "Beridze");
  await user.type(screen.getByLabelText(/Email/i), "nino@example.com");
  await user.type(screen.getByLabelText(/Phone/i), "+995555123456");
  await user.click(screen.getByRole("button", { name: "Save contact" }));
}

describe("CheckoutPageView", () => {
  beforeEach(() => {
    resetCartStoreForTests();
    window.sessionStorage.clear();
    vi.mocked(createCheckoutSession).mockClear();
    vi.mocked(updateCheckoutContact).mockClear();
    vi.mocked(updateCheckoutFulfillment).mockClear();
    vi.mocked(createCheckoutQuote).mockClear();
  });

  it("shows an empty state when the cart has no items", () => {
    replaceCart(emptyCart());
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    expect(screen.getByText("Your cart is empty")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "View cart" })).toBeInTheDocument();
  });

  it("loads a guest checkout session and keeps the public id out of the URL", async () => {
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    expect(
      await screen.findByRole("heading", { name: "Checkout" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/First name/i)).toBeInTheDocument();
    expect(window.location.pathname).not.toContain("11111111");
    expect(window.location.search).not.toContain("555");
  });

  it("validates contact fields before continuing", async () => {
    const user = userEvent.setup();
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    await user.click(screen.getByRole("button", { name: "Save contact" }));
    expect(
      screen.getByRole("button", { name: "Save contact" }),
    ).toBeInTheDocument();
    expect(updateCheckoutContact).not.toHaveBeenCalled();
  });

  it("moves to delivery after valid contact and hides street for pickup", async () => {
    const user = userEvent.setup();
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    await fillContact(user);
    expect(await screen.findByText("Store pickup")).toBeInTheDocument();
    expect(screen.getByText("Local delivery")).toBeInTheDocument();
    expect(
      screen.getByText("Not available for this address"),
    ).toBeInTheDocument();
    await user.click(screen.getByRole("radio", { name: /Store pickup/i }));
    expect(screen.queryByLabelText(/Street/i)).not.toBeInTheDocument();
  });

  it("shows street fields for delivery methods", async () => {
    const user = userEvent.setup();
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    await fillContact(user);
    await user.click(screen.getByRole("radio", { name: /Local delivery/i }));
    expect(
      await screen.findByLabelText(/City or municipality/i),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/Street/i)).toBeInTheDocument();
  });

  it("creates a quote and enables confirm order", async () => {
    const user = userEvent.setup();
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    await fillContact(user);
    await user.click(screen.getByRole("radio", { name: /Store pickup/i }));
    await user.click(screen.getByRole("button", { name: "Save delivery" }));
    expect(
      await screen.findByRole("button", { name: "Review totals" }),
    ).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Review totals" }));
    expect(await screen.findByText("Alpine Scope")).toBeInTheDocument();
    expect(screen.getByText("Quoted total")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Confirm order" })).toBeEnabled();
    expect(screen.queryByText(/payment successful/i)).not.toBeInTheDocument();
    expect(createCheckoutQuote).toHaveBeenCalled();
  });

  it("submits confirm order with session, quote and version", async () => {
    const user = userEvent.setup();
    vi.mocked(createOrder).mockResolvedValue({
      id: "33333333-3333-3333-3333-333333333333",
      order_number: "ORD-20260922-A7K9P2",
      status: "pending_payment",
      payment_status: "unpaid",
      fulfillment_status: "unfulfilled",
      currency: "GEL",
      placed_at: new Date().toISOString(),
      payment_expires_at: new Date(Date.now() + 20 * 60 * 1000).toISOString(),
      quote_revision: 1,
      contact: {
        first_name: "Nino",
        last_name: "Beridze",
        email: "nino@example.com",
        phone: "+995555123456",
        customer_note: null,
      },
      address: null,
      items: [],
      fulfillment: {
        method_code: "store_pickup",
        method_type: "store_pickup",
        name: "Store pickup",
        delivery_total_minor: 0,
        estimated_min_days: null,
        estimated_max_days: null,
        pickup_location: null,
      },
      totals: quote.totals,
      adjustments: [],
      can_cancel: true,
    });
    replaceCart({ ...cart, status: "active" });
    render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    await fillContact(user);
    await user.click(screen.getByRole("radio", { name: /Store pickup/i }));
    await user.click(screen.getByRole("button", { name: "Save delivery" }));
    await user.click(
      await screen.findByRole("button", { name: "Review totals" }),
    );
    await user.click(
      await screen.findByRole("button", { name: "Confirm order" }),
    );
    expect(createOrder).toHaveBeenCalledWith(
      expect.objectContaining({
        checkoutSessionId: session.data.checkout_session.id,
        quoteId: quote.id,
        checkoutVersion: 4,
      }),
    );
  });

  it("does not show a fake order success action", async () => {
    replaceCart({ ...cart, status: "active" });
    const { container } = render(
      <TestProviders>
        <CheckoutPageView />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Checkout" });
    expect(screen.queryByText(/order placed/i)).not.toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });
});
