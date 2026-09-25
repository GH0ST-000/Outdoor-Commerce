import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { FulfillmentTrackingSection } from "@/features/orders/components/FulfillmentTrackingSection";
import { TestProviders } from "@/test/providers";
import type { Order } from "@/features/orders/types";

function paidOrder(overrides: Partial<Order> = {}): Order {
  return {
    id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
    order_number: "ORD-20260923-TEST",
    status: "confirmed",
    payment_status: "paid",
    fulfillment_status: "processing",
    currency: "GEL",
    placed_at: new Date().toISOString(),
    payment_expires_at: null,
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
      pickup_location: { id: "loc", name: "Tbilisi store", address: "Tbilisi" },
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
    can_cancel: false,
    fulfillment_progress: {
      order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
      fulfillment_status: "processing",
      remaining_items: [
        {
          order_item_id: "item-2",
          name: "Line",
          variant_name: "Black",
          quantity: 1,
        },
      ],
      capabilities: { can_refresh: true, poll: true },
      shipments: [
        {
          id: "ship-1",
          shipment_number: "SHP-20260923-A7K9",
          type: "store_pickup",
          status: "ready_for_pickup",
          provider: {
            code: "manual",
            name: "Manual fulfillment",
            manual: true,
          },
          tracking: { number: null, url: null },
          items: [
            {
              order_item_id: "item-1",
              name: "Alpine Scope",
              variant_name: "Black",
              quantity: 1,
              media: null,
            },
          ],
          timeline: [
            {
              status: "ready_for_pickup",
              message: "Ready for pickup",
              occurred_at: new Date().toISOString(),
              location: null,
            },
          ],
          pickup_location: {
            id: "loc",
            name: "Tbilisi store",
            address: "Tbilisi",
          },
          estimated_delivery: { from: null, to: null, is_guaranteed: false },
          shipped_at: null,
          delivered_at: null,
          collected_at: null,
          exception: null,
        },
      ],
    },
    ...overrides,
  };
}

describe("FulfillmentTrackingSection", () => {
  it("renders pickup timeline without internal notes or warehouse ids", async () => {
    const user = userEvent.setup();
    const onRefresh = vi.fn(async () => undefined);
    const { container } = render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={paidOrder()}
          localeTag="en"
          onRefresh={onRefresh}
        />
      </TestProviders>,
    );

    expect(
      screen.getByText("Store pickup SHP-20260923-A7K9"),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Ready for pickup").length).toBeGreaterThan(0);
    expect(screen.getByText(/Still to send/i)).toBeInTheDocument();
    expect(screen.queryByText(/aisle/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/warehouse_id/i)).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /refresh tracking/i }));
    expect(onRefresh).toHaveBeenCalled();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("renders a safe tracking link and hides javascript urls", () => {
    const order = paidOrder({
      fulfillment_status: "partially_fulfilled",
      fulfillment_progress: {
        order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
        fulfillment_status: "partially_fulfilled",
        remaining_items: [],
        capabilities: { can_refresh: true, poll: false },
        shipments: [
          {
            id: "ship-2",
            shipment_number: "SHP-2",
            type: "delivery",
            status: "in_transit",
            provider: { code: "manual", name: "Manual courier", manual: true },
            tracking: {
              number: "TRACK-1",
              url: "https://tracking.example.com/TRACK-1",
            },
            items: [],
            timeline: [],
            pickup_location: null,
            estimated_delivery: { from: null, to: null, is_guaranteed: false },
            shipped_at: new Date().toISOString(),
            delivered_at: null,
            collected_at: null,
            exception: null,
          },
        ],
      },
    });

    render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={order}
          localeTag="en"
          onRefresh={async () => undefined}
        />
      </TestProviders>,
    );

    const link = screen.getByRole("link", { name: /opens in a new tab/i });
    expect(link).toHaveAttribute(
      "href",
      "https://tracking.example.com/TRACK-1",
    );
  });

  it("shows exception copy without blaming the customer", () => {
    render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={paidOrder({
            fulfillment_status: "exception",
            fulfillment_progress: {
              order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
              fulfillment_status: "exception",
              remaining_items: [],
              capabilities: { can_refresh: true, poll: true },
              shipments: [],
            },
          })}
          localeTag="en"
          onRefresh={async () => undefined}
        />
      </TestProviders>,
    );

    expect(screen.getByText(/needs a short review/i)).toBeInTheDocument();
  });

  it("renders unfulfilled, delivered, collected, and failed-attempt states", () => {
    render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={paidOrder({
            fulfillment_status: "unfulfilled",
            fulfillment_progress: {
              order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
              fulfillment_status: "unfulfilled",
              remaining_items: [],
              capabilities: { can_refresh: true, poll: false },
              shipments: [],
            },
          })}
          localeTag="en"
          onRefresh={async () => undefined}
        />
      </TestProviders>,
    );
    expect(screen.getByText(/has not started yet/i)).toBeInTheDocument();
  });

  it("does not render a javascript tracking url", () => {
    const order = paidOrder({
      fulfillment_progress: {
        order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
        fulfillment_status: "processing",
        remaining_items: [],
        capabilities: { can_refresh: false, poll: false },
        shipments: [
          {
            id: "ship-js",
            shipment_number: "SHP-JS",
            type: "delivery",
            status: "shipped",
            provider: { code: "manual", name: "Manual courier", manual: true },
            tracking: { number: "X", url: "javascript:alert(1)" },
            items: [],
            timeline: [],
            pickup_location: null,
            estimated_delivery: { from: null, to: null, is_guaranteed: false },
            shipped_at: new Date().toISOString(),
            delivered_at: null,
            collected_at: null,
            exception: null,
          },
        ],
      },
    });

    render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={order}
          localeTag="en"
          onRefresh={async () => undefined}
        />
      </TestProviders>,
    );

    expect(screen.queryByRole("link")).not.toBeInTheDocument();
    expect(screen.getByText(/Tracking number/i)).toBeInTheDocument();
  });

  it("renders delivered and collected copy", () => {
    render(
      <TestProviders>
        <FulfillmentTrackingSection
          order={paidOrder({
            fulfillment_status: "fulfilled",
            fulfillment_progress: {
              order_id: "bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb",
              fulfillment_status: "fulfilled",
              remaining_items: [],
              capabilities: { can_refresh: true, poll: false },
              shipments: [
                {
                  id: "ship-d",
                  shipment_number: "SHP-D",
                  type: "delivery",
                  status: "delivered",
                  provider: {
                    code: "manual",
                    name: "Manual courier",
                    manual: true,
                  },
                  tracking: { number: null, url: null },
                  items: [],
                  timeline: [
                    {
                      status: "delivered",
                      message: "Delivered",
                      occurred_at: new Date().toISOString(),
                      location: null,
                    },
                  ],
                  pickup_location: null,
                  estimated_delivery: {
                    from: null,
                    to: null,
                    is_guaranteed: false,
                  },
                  shipped_at: new Date().toISOString(),
                  delivered_at: new Date().toISOString(),
                  collected_at: null,
                  exception: null,
                },
              ],
            },
          })}
          localeTag="en"
          onRefresh={async () => undefined}
        />
      </TestProviders>,
    );

    expect(screen.getAllByText("Delivered").length).toBeGreaterThan(0);
  });
});
