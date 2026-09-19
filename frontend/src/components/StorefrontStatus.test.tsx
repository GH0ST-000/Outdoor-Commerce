import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { StorefrontStatus } from "@/components/StorefrontStatus";

describe("StorefrontStatus", () => {
  it("renders the ready status message", () => {
    render(<StorefrontStatus />);

    expect(screen.getByTestId("storefront-status")).toHaveTextContent(
      "Frontend health: ready",
    );
  });
});
