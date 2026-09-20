import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import { PRODUCT_IMAGE_PLACEHOLDER } from "@/features/storefront/media/placeholder";
import { TestProviders } from "@/test/providers";

describe("ResponsiveProductImage", () => {
  it("uses the local placeholder when no src is available", () => {
    render(
      <TestProviders>
        <div className="relative h-40 w-32">
          <ResponsiveProductImage
            media={undefined}
            alt={{ en: "Missing product", ka: "პროდუქტი" }}
            locale="en"
            fill
          />
        </div>
      </TestProviders>,
    );

    const img = screen.getByRole("img", { name: "Missing product" });
    expect(img).toHaveAttribute("src", expect.stringContaining(PRODUCT_IMAGE_PLACEHOLDER));
  });
});
