import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { OrderConfirmationPageView } from "@/features/orders/components/OrderConfirmationPageView";
import { TestProviders } from "@/test/providers";
import { getOrder, cancelOrder } from "@/features/orders/api/order-client";
import type { Order } from "@/features/orders/types";
import { resetCartStoreForTests } from "@/features/cart/state/cart-store";

const order: Order = {
  id: "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
  order_number: "ORD-20260922-A7K9P2",
  status: "pending_payment",
  payment_status: "unpaid",
  fulfillment_status: "unfulfilled",
  currency: "GEL",
  placed_at: new Date().toISOString(),
  payment_expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
  quote_revision: 1,
  contact: {
    first_name: "Nino",
    last_name: "Beridze",
    email: "nino@example.com",
    phone: "+995555123456",
    customer_note: null,
  },
  address: {
    recipient_first_name: "Nino",
    recipient_last_name: "Beridze",
    country_code: "GE",
    region: null,
    municipality_or_city: "Tbilisi",
    district: null,
    street: "Rustaveli",
    house_number: "10",
    apartment: null,
    postal_code: null,
  },
  items: [
    {
      id: "item-1",
      sku: "SKU-1",
      name: "Alpine Scope",
      variant_name: "Black",
      attributes: [],
      quantity: 2,
      media: null,
      pricing: {
        unit_base_price_minor: 12000,
        unit_effective_price_minor: 10000,
        line_subtotal_minor: 24000,
        line_discount_minor: 4000,
        line_total_minor: 20000,
        currency: "GEL",
      },
    },
  ],
  fulfillment: {
    method_code: "local_delivery",
    method_type: "local_delivery",
    name: "Local delivery",
    delivery_total_minor: 500,
    estimated_min_days: null,
    estimated_max_days: null,
    pickup_location: null,
  },
  totals: {
    items_subtotal_minor: 24000,
    discount_total_minor: 4000,
    delivery_total_minor: 500,
    tax_total_minor: 0,
    grand_total_minor: 20500,
    currency: "GEL",
    price_includes_tax: true,
  },
  adjustments: [],
  can_cancel: true,
};

vi.mock("@/features/orders/api/order-client", () => ({
  getOrder: vi.fn(),
  cancelOrder: vi.fn(),
}));

vi.mock("@/features/payments/api/payment-client", () => ({
  listPaymentMethods: vi.fn().mockResolvedValue([]),
  createPaymentAttempt: vi.fn(),
  cancelPaymentAttempt: vi.fn(),
  getCurrentPaymentAttempt: vi.fn(),
  isSafeRedirectUrl: () => true,
}));

describe("OrderConfirmationPageView", () => {
  beforeEach(() => {
    resetCartStoreForTests();
    vi.mocked(getOrder).mockReset();
    vi.mocked(cancelOrder).mockReset();
    vi.mocked(getOrder).mockResolvedValue(order);
  });

  it("renders pending-payment confirmation without claiming payment success", async () => {
    const { container } = render(
      <TestProviders>
        <OrderConfirmationPageView orderPublicId={order.id} />
      </TestProviders>,
    );
    expect(
      await screen.findByRole("heading", { name: "Order created" }),
    ).toBeInTheDocument();
    expect(screen.getByText("ORD-20260922-A7K9P2")).toBeInTheDocument();
    expect(screen.getByText("Alpine Scope")).toBeInTheDocument();
    expect(screen.getByText(/Payment is still pending/i)).toBeInTheDocument();
    expect(screen.queryByText(/payment successful/i)).not.toBeInTheDocument();
    expect(
      screen.queryByText(/your order has shipped/i),
    ).not.toBeInTheDocument();
    expect(window.location.pathname).not.toContain("token");
    expect(await axe(container)).toHaveNoViolations();
  });

  it("cancels an unpaid order", async () => {
    const user = userEvent.setup();
    vi.mocked(cancelOrder).mockResolvedValue({
      ...order,
      status: "cancelled",
      payment_status: "cancelled",
      can_cancel: false,
    });
    render(
      <TestProviders>
        <OrderConfirmationPageView orderPublicId={order.id} />
      </TestProviders>,
    );
    await screen.findByRole("heading", { name: "Order created" });
    await user.click(
      screen.getByRole("button", { name: "Cancel unpaid order" }),
    );
    expect(
      await screen.findByRole("heading", { name: "Order cancelled" }),
    ).toBeInTheDocument();
    expect(cancelOrder).toHaveBeenCalledWith(order.id);
  });

  it("shows a generic missing state when unauthorized", async () => {
    const { ApiClientError } = await import("@/lib/api-client");
    vi.mocked(getOrder).mockRejectedValue(
      new ApiClientError({
        status: 404,
        code: "ORDER_NOT_FOUND",
        message: "Order was not found.",
      }),
    );
    render(
      <TestProviders>
        <OrderConfirmationPageView orderPublicId={order.id} />
      </TestProviders>,
    );
    expect(await screen.findByText("Order was not found.")).toBeInTheDocument();
  });
});
