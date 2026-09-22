import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { TestProviders } from "@/test/providers";
import { QuoteSummary } from "@/features/checkout/components/QuoteSummary";
import type { CheckoutQuote } from "@/features/checkout/types";

const quote: CheckoutQuote = {
  id: "quote-1",
  revision: 1,
  status: "active",
  currency: "GEL",
  expires_at: new Date(Date.now() + 10 * 60 * 1000).toISOString(),
  remaining_seconds: 600,
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
      attributes: [],
      promotions: [
        { code: "autumn", name: "Autumn offer", discount_type: "percentage" },
      ],
      restriction: null,
      issues: [],
    },
  ],
  fulfillment: {
    method_code: "local_delivery",
    name: "Local delivery",
    amount_minor: 500,
    estimated_min_days: null,
    estimated_max_days: null,
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
  adjustments: [
    {
      type: "promotion",
      code: "autumn",
      label: "Autumn offer",
      amount_minor: -4000,
    },
  ],
  restrictions: [],
  fingerprint: "fp",
};

describe("QuoteSummary", () => {
  it("renders Laravel totals, promotions, and delivery", () => {
    render(
      <TestProviders>
        <QuoteSummary quote={quote} expired={false} remainingLabel="9:59" />
      </TestProviders>,
    );

    expect(screen.getByText("Alpine Scope")).toBeInTheDocument();
    expect(screen.getByText("Autumn offer")).toBeInTheDocument();
    expect(screen.getByText("Quoted total")).toBeInTheDocument();
    expect(screen.getByText(/Quote expires/i)).toBeInTheDocument();
  });

  it("announces expiration without a fake order confirmation", () => {
    render(
      <TestProviders>
        <QuoteSummary quote={quote} expired remainingLabel="" />
      </TestProviders>,
    );

    expect(screen.getByText(/This quote has expired/i)).toBeInTheDocument();
    expect(screen.queryByText(/order placed/i)).not.toBeInTheDocument();
  });
});
