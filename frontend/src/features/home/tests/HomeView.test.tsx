import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { HomeView } from "@/features/home/components/HomeView";
import { getHomePageContent } from "@/features/home/content";
import type { HomePageData } from "@/features/home/api/get-home-page-data";
import { TestProviders } from "@/test/providers";

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const priced = {
  id: "12",
  slug: "optika",
  brand: "Condor",
  name: { en: "Optics", ka: "ოპტიკა" },
  href: "/products/optika",
  imageSrc: "/storefront/product-placeholder.svg",
  imageAlt: { en: "Optics", ka: "ოპტიკა" },
  pricing: {
    currency: "GEL",
    min_amount_minor: 11049,
    max_amount_minor: 12999,
    is_range: true,
    final_amount_minor: 11049,
    base_amount_minor: 12999,
  },
  availabilityLabel: { en: "In stock", ka: "მარაგშია" },
  availabilityStatus: "in_stock" as const,
};

function data(overrides: Partial<HomePageData> = {}): HomePageData {
  return {
    locale: "ka",
    gateway: [
      {
        slug: "hunting",
        name: "ნადირობა",
        description: "რელიეფისთვის მზა სისტემები",
        href: "/catalog/hunting",
        imageSrc: "/storefront/product-placeholder.svg",
        span: "wide",
      },
    ],
    categoriesFailed: false,
    featured: [priced],
    featuredFailed: false,
    onSale: [],
    onSaleFailed: false,
    brands: [
      {
        id: 1,
        name: "Condor",
        slug: "condor",
        path: "/brands/condor",
        is_featured: true,
        product_count: 3,
        used_fallback: false,
      },
    ],
    brandsFailed: false,
    ...overrides,
  };
}

describe("HomeView", () => {
  it("renders Georgian hero copy and catalog CTAs", () => {
    render(
      <TestProviders locale="ka">
        <HomeView data={data()} content={getHomePageContent("ka")} />
      </TestProviders>,
    );

    expect(
      screen.getByRole("heading", {
        level: 1,
        name: /ველური საქართველო/,
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: /კატალოგის ნახვა/ }),
    ).toHaveAttribute("href", "/catalog");
    expect(
      screen.getByRole("link", { name: /საველე გზამკვლევი/ }),
    ).toHaveAttribute("href", "/field-guide");
    expect(screen.getByRole("link", { name: /ნადირობა/ })).toHaveAttribute(
      "href",
      "/catalog/hunting",
    );
  });

  it("renders featured API prices and omits failed optional sections", () => {
    render(
      <TestProviders locale="en">
        <HomeView
          data={data({
            locale: "en",
            featured: [priced],
            brandsFailed: true,
            brands: null,
            onSaleFailed: true,
            onSale: null,
          })}
          content={getHomePageContent("en")}
        />
      </TestProviders>,
    );

    expect(screen.getAllByText("Optics").length).toBeGreaterThan(0);
    expect(screen.getByTestId("price-display-range")).toBeInTheDocument();
    expect(screen.queryByText("Trusted makers")).not.toBeInTheDocument();
    expect(screen.queryByText("Current discounts")).not.toBeInTheDocument();
  });

  it("labels season and map teasers as previews", () => {
    render(
      <TestProviders locale="ka">
        <HomeView data={data()} content={getHomePageContent("ka")} />
      </TestProviders>,
    );
    expect(screen.getAllByText(/ინტერფეისის გადახედვა/).length).toBeGreaterThan(
      0,
    );
    expect(
      screen.getAllByText(/არ არის ოფიციალური სანადირო კანონი/).length,
    ).toBeGreaterThan(0);
    expect(screen.queryByText(/best selling/i)).not.toBeInTheDocument();
  });

  it("exposes landmarks without axe violations", async () => {
    const { container } = render(
      <TestProviders locale="ka">
        <HomeView data={data()} content={getHomePageContent("ka")} />
      </TestProviders>,
    );
    expect(screen.getAllByRole("heading", { level: 1 })).toHaveLength(1);
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });
});
