import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";

describe("PriceDisplay", () => {
  it("shows single price", () => {
    render(<PriceDisplay amountMinor={12999} currency="GEL" locale="en" />);
    expect(screen.getByTestId("price-display-single")).toBeInTheDocument();
  });

  it("shows range", () => {
    render(
      <PriceDisplay
        isRange
        minAmountMinor={12000}
        maxAmountMinor={18500}
        currency="GEL"
        locale="en"
      />,
    );
    expect(screen.getByTestId("price-display-range")).toBeInTheDocument();
  });

  it("shows base and final when discounted", () => {
    render(
      <PriceDisplay
        baseAmountMinor={20000}
        finalAmountMinor={15000}
        currency="GEL"
        locale="en"
      />,
    );
    expect(screen.getByTestId("price-display-discounted")).toBeInTheDocument();
  });

  it("does not show zero for missing price", () => {
    render(<PriceDisplay amountMinor={null} missingLabel="Price on request" />);
    expect(screen.getByTestId("price-display-missing")).toHaveTextContent(
      "Price on request",
    );
    expect(screen.queryByText(/0\.00/)).not.toBeInTheDocument();
  });
});
