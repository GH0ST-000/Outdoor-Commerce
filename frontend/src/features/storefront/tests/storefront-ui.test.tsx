import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { SiteHeader } from "@/features/storefront/components/layout/SiteHeader";
import { ProductCard } from "@/features/storefront/components/commerce/ProductCard";
import { VariantSelector } from "@/features/storefront/components/commerce/VariantSelector";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { TestProviders } from "@/test/providers";
import { featuredProducts } from "@/features/storefront/fixtures/demo-catalog";
import { productDetails } from "@/features/storefront/fixtures/demo-catalog";

vi.mock("next/navigation", () => ({
  usePathname: () => "/",
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

describe("storefront chrome", () => {
  it("opens and closes mobile navigation", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <SiteHeader />
      </TestProviders>,
    );

    await user.click(screen.getByRole("button", { name: /open menu|მენიუს გახსნა/i }));
    expect(screen.getByRole("navigation", { name: /mobile/i })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /close menu|მენიუს დახურვა/i }));
    expect(screen.queryByRole("navigation", { name: /mobile/i })).not.toBeInTheDocument();
  });

  it("opens search and closes with Escape", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <SiteHeader />
      </TestProviders>,
    );
    await user.click(screen.getByRole("button", { name: /search|ძიება/i }));
    expect(screen.getByRole("dialog")).toBeInTheDocument();
    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });
});

describe("product presentation", () => {
  it("renders product card name and missing-price fallback", () => {
    const product = { ...featuredProducts[0]!, priceLabel: undefined };
    render(
      <TestProviders>
        <ProductCard product={product} />
      </TestProviders>,
    );
    expect(screen.getByText(product.name.en)).toBeInTheDocument();
    expect(screen.getByText(/price on request|ფასი მოთხოვნით/i)).toBeInTheDocument();
  });

  it("exposes color value names to assistive tech", () => {
    const axes = productDetails["alpine-hunting-jacket"]!.variants.axes;
    render(
      <TestProviders>
        <VariantSelector axes={axes} />
      </TestProviders>,
    );
    expect(
      screen.getByRole("button", { name: /forest green|ტყის მწვანე/i }),
    ).toBeInTheDocument();
  });
});

describe("legal demo", () => {
  it("shows demo disclaimer banner", () => {
    render(
      <TestProviders>
        <LegalDemoBanner />
      </TestProviders>,
    );
    expect(
      screen.getByRole("note"),
    ).toHaveTextContent(/demonstration|სადემონსტრაციო/i);
  });
});
