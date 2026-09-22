import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { PaymentSection } from "@/features/payments/components/PaymentSection";
import { PaymentReturnPageView } from "@/features/payments/components/PaymentReturnPageView";
import { TestProviders } from "@/test/providers";
import {
  listPaymentMethods,
  createPaymentAttempt,
} from "@/features/payments/api/payment-client";
import { getOrder } from "@/features/orders/api/order-client";
import type { Order } from "@/features/orders/types";
import type { PaymentAttempt, PaymentMethod } from "@/features/payments/types";

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
  address: null,
  items: [],
  fulfillment: {
    method_code: "store_pickup",
    method_type: "store_pickup",
    name: "Pickup",
    delivery_total_minor: 0,
    estimated_min_days: null,
    estimated_max_days: null,
    pickup_location: { id: "1", name: "Store", address: "Tbilisi" },
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
  can_cancel: true,
  can_pay: true,
  can_retry_payment: false,
  current_payment_attempt: null,
};

const method: PaymentMethod = {
  code: "test_hosted_redirect",
  type: "hosted_redirect",
  name: "Test hosted payment",
  description: "Development-only test provider. Not a real bank.",
  icon: "test",
  development_only: true,
  supported_currencies: ["GEL"],
};

const attempt: PaymentAttempt = {
  id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
  order_id: order.id,
  status: "requires_action",
  payment_method: { code: method.code, name: method.name },
  amount: { amount_minor: 10000, currency: "GEL" },
  action: {
    type: "redirect",
    url: "http://localhost:3000/payment/test/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
    method: "GET",
    expires_at: null,
  },
  failure: null,
  order: {
    status: "payment_processing",
    payment_status: "pending",
    payment_expires_at: order.payment_expires_at,
  },
};

vi.mock("@/features/payments/api/payment-client", async () => {
  const actual = await vi.importActual<
    typeof import("@/features/payments/api/payment-client")
  >("@/features/payments/api/payment-client");
  return {
    ...actual,
    listPaymentMethods: vi.fn(),
    createPaymentAttempt: vi.fn(),
    cancelPaymentAttempt: vi.fn(),
    getCurrentPaymentAttempt: vi.fn().mockResolvedValue(null),
  };
});

vi.mock("@/features/orders/api/order-client", () => ({
  getOrder: vi.fn(),
  cancelOrder: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useSearchParams: () =>
    new URLSearchParams(
      "order_id=aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa&success=true",
    ),
  useRouter: () => ({ replace: vi.fn() }),
}));

describe("PaymentSection", () => {
  beforeEach(() => {
    vi.mocked(listPaymentMethods).mockReset();
    vi.mocked(createPaymentAttempt).mockReset();
    vi.mocked(listPaymentMethods).mockResolvedValue([method]);
  });

  it("lists methods, starts a hosted redirect, and has no card fields", async () => {
    const user = userEvent.setup();
    const assign = vi.fn();
    vi.stubGlobal("location", { ...window.location, assign });
    vi.mocked(createPaymentAttempt).mockResolvedValue(attempt);
    const { container } = render(
      <TestProviders>
        <PaymentSection
          order={order}
          localeTag="en"
          onOrderRefresh={async () => undefined}
        />
      </TestProviders>,
    );
    expect(await screen.findByText("Test hosted payment")).toBeInTheDocument();
    expect(screen.getAllByText(/Test provider/i).length).toBeGreaterThan(0);
    expect(screen.queryByLabelText(/card number/i)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/cvv/i)).not.toBeInTheDocument();
    await user.click(
      screen.getByRole("button", { name: "Continue to payment" }),
    );
    expect(createPaymentAttempt).toHaveBeenCalledTimes(1);
    expect(assign).toHaveBeenCalled();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("shows an accurate empty state when no methods exist", async () => {
    vi.mocked(listPaymentMethods).mockResolvedValue([]);
    render(
      <TestProviders>
        <PaymentSection
          order={order}
          localeTag="en"
          onOrderRefresh={async () => undefined}
        />
      </TestProviders>,
    );
    expect(
      await screen.findByText(/not available for this order/i),
    ).toBeInTheDocument();
  });
});

describe("PaymentReturnPageView", () => {
  beforeEach(() => {
    vi.mocked(getOrder).mockReset();
  });

  it("does not treat success query parameters as verified payment", async () => {
    vi.mocked(getOrder).mockResolvedValue(order);
    render(
      <TestProviders>
        <PaymentReturnPageView />
      </TestProviders>,
    );
    expect(
      await screen.findByRole("heading", { name: "Confirming payment" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Payment confirmed")).not.toBeInTheDocument();
  });
});
