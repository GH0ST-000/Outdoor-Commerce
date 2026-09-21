import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { ProductDetailPage } from "@/features/storefront/components/ProductDetailPage";
import { TestProviders } from "@/test/providers";
import type { PublicProductDetail } from "@/features/catalog/types/public-catalog";

vi.mock("next/navigation", () => ({
  usePathname: () => "/products/optika",
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const detail: PublicProductDetail = {
  id: 12,
  name: "Optics",
  slug: "optika",
  short_description: "Short",
  description: "<p>Full</p>",
  model_number: "M-1",
  is_featured: false,
  used_fallback: false,
  brand: { id: 1, name: "Condor", slug: "condor", path: "/brands/condor" },
  primary_category: {
    id: 2,
    name: "Hunting",
    slug: "hunting",
    path: "/catalog/hunting",
  },
  categories: [],
  breadcrumbs: [{ name: "Hunting", slug: "hunting", path: "/catalog/hunting" }],
  gallery: [
    {
      alt: "Product",
      caption: null,
      width: 800,
      height: 1000,
      aspect_ratio: 0.8,
      dominant_color: null,
      focal_point: null,
      sources: {
        jpeg: {
          srcset: "/media/a.jpg 800w",
          presets: {
            card: {
              url: "/media/a.jpg",
              width: 800,
              height: 1000,
              byte_size: 12,
            },
            detail: {
              url: "/media/a-detail.jpg",
              width: 1600,
              height: 2000,
              byte_size: 24,
            },
          },
        },
      },
    },
    {
      alt: "Product back",
      caption: null,
      width: 800,
      height: 1000,
      aspect_ratio: 0.8,
      dominant_color: null,
      focal_point: null,
      sources: {
        jpeg: {
          srcset: "/media/b.jpg 800w",
          presets: {
            card: {
              url: "/media/b.jpg",
              width: 800,
              height: 1000,
              byte_size: 12,
            },
          },
        },
      },
    },
  ],
  price: {
    currency: "GEL",
    min_final_amount_minor: 8000,
    max_final_amount_minor: 11000,
    min_base_amount_minor: 11000,
    is_range: true,
    on_sale: true,
  },
  availability: { status: "in_stock", purchasable: true, low_stock: false },
  variants: {
    axes: [
      {
        id: 9,
        code: "color",
        name: "Color",
        type: "select",
        values: [
          { id: 1, code: "black", name: "Black", color_hex: null },
          { id: 2, code: "green", name: "Green", color_hex: null },
        ],
      },
    ],
    combinations: [
      {
        id: 501,
        sku: "PRD-BLACK",
        is_default: true,
        combination_label: "Black",
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "black", name: "Black", color_hex: null },
          },
        ],
        media: [],
        price: {
          currency: "GEL",
          base_amount_minor: 11000,
          final_amount_minor: 8000,
          discount_amount_minor: 3000,
          on_sale: true,
          applied_promotions: [],
          calculated_at: "2026-09-21T00:00:00Z",
          signature: "a",
        },
        availability: {
          status: "in_stock",
          purchasable: true,
          low_stock: false,
        },
      },
      {
        id: 502,
        sku: "PRD-GREEN",
        is_default: false,
        combination_label: "Green",
        attributes: [
          {
            code: "color",
            name: "Color",
            value: { code: "green", name: "Green", color_hex: null },
          },
        ],
        media: [
          {
            alt: "Green variant",
            caption: null,
            width: 800,
            height: 1000,
            aspect_ratio: 0.8,
            dominant_color: null,
            focal_point: null,
            sources: {
              jpeg: {
                srcset: "/media/green.jpg 800w",
                presets: {
                  card: {
                    url: "/media/green.jpg",
                    width: 800,
                    height: 1000,
                    byte_size: 12,
                  },
                },
              },
            },
          },
        ],
        price: {
          currency: "GEL",
          base_amount_minor: 11000,
          final_amount_minor: 11000,
          discount_amount_minor: 0,
          on_sale: false,
          applied_promotions: [],
          calculated_at: "2026-09-21T00:00:00Z",
          signature: "b",
        },
        availability: {
          status: "out_of_stock",
          purchasable: false,
          low_stock: false,
        },
      },
    ],
    default_variant_id: 501,
  },
  default_variant_id: 501,
  seo: {
    title: "Optics",
    description: null,
    canonical_path: "/products/optika",
    alternate_locale_paths: {},
    open_graph_media: null,
    robots: "index,follow",
  },
  canonical_path: "/products/optika",
  alternate_locale_paths: {},
  updated_at: null,
};

describe("ProductDetailPage API integration", () => {
  it("updates price sku media and availability when a variant is selected", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <ProductDetailPage slug="optika" detail={detail} />
      </TestProviders>,
    );

    expect(
      screen.getByRole("heading", { level: 1, name: "Optics" }),
    ).toBeInTheDocument();
    expect(screen.getByTestId("product-sku")).toHaveTextContent("PRD-BLACK");
    expect(screen.getByText("1 / 2")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Next image" }));
    expect(screen.getByText("2 / 2")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /Green/i }));
    expect(screen.getByTestId("product-sku")).toHaveTextContent("PRD-GREEN");
    expect(
      screen.getAllByText(/out of stock|არ არის მარაგში/i).length,
    ).toBeGreaterThan(0);
    expect(screen.getByAltText("Green variant")).toBeInTheDocument();
    expect(screen.getByTestId("product-purchase-action")).toBeDisabled();
  });

  it("selects a URL variant on the initial render", () => {
    render(
      <TestProviders>
        <ProductDetailPage
          slug="optika"
          detail={detail}
          initialVariantId={502}
        />
      </TestProviders>,
    );
    expect(screen.getByTestId("product-sku")).toHaveTextContent("PRD-GREEN");
  });

  it("falls back from an invalid URL variant", () => {
    render(
      <TestProviders>
        <ProductDetailPage
          slug="optika"
          detail={detail}
          initialVariantId={999}
        />
      </TestProviders>,
    );
    expect(screen.getByTestId("product-sku")).toHaveTextContent("PRD-BLACK");
  });

  it("does not show zero when price is missing", () => {
    const missing = {
      ...detail,
      variants: {
        ...detail.variants,
        combinations: detail.variants.combinations.map((item) =>
          item.id === 501
            ? {
                ...item,
                price: {
                  ...item.price,
                  final_amount_minor: null,
                  base_amount_minor: null,
                  signature: null,
                },
                availability: {
                  status: "unavailable" as const,
                  purchasable: false,
                  low_stock: false,
                },
              }
            : item,
        ),
      },
    };
    render(
      <TestProviders>
        <ProductDetailPage slug="optika" detail={missing} />
      </TestProviders>,
    );
    expect(
      screen.getAllByTestId("price-display-missing").length,
    ).toBeGreaterThan(0);
    expect(screen.queryByText("0")).not.toBeInTheDocument();
    expect(screen.getByTestId("product-purchase-action")).toBeDisabled();
  });

  it("omits an empty description and excludes the current product from related", () => {
    const emptyDescription = { ...detail, description: null };
    render(
      <TestProviders>
        <ProductDetailPage
          slug="optika"
          detail={emptyDescription}
          related={[
            {
              id: "12",
              slug: "optika",
              brand: "Condor",
              name: { en: "Optics", ka: "ოპტიკა" },
              href: "/products/optika",
              imageSrc: "/storefront/product-placeholder.svg",
              imageAlt: { en: "Optics", ka: "ოპტიკა" },
              pricing: {
                currency: "GEL",
                min_amount_minor: 8000,
                max_amount_minor: 8000,
                is_range: false,
                final_amount_minor: 8000,
              },
              availabilityLabel: { en: "In stock", ka: "მარაგშია" },
              availabilityStatus: "in_stock",
            },
            {
              id: "99",
              slug: "jacket",
              brand: "Condor",
              name: { en: "Jacket", ka: "ქურქი" },
              href: "/products/jacket",
              imageSrc: "/storefront/product-placeholder.svg",
              imageAlt: { en: "Jacket", ka: "ქურქი" },
              pricing: {
                currency: "GEL",
                min_amount_minor: 9000,
                max_amount_minor: 9000,
                is_range: false,
                final_amount_minor: 9000,
              },
              availabilityLabel: { en: "In stock", ka: "მარაგშია" },
              availabilityStatus: "in_stock",
            },
          ]}
        />
      </TestProviders>,
    );
    expect(
      screen.queryByRole("heading", { name: "Description" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "More from this category" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Jacket")).toBeInTheDocument();
  });

  it("renders Georgian product labels", () => {
    render(
      <TestProviders locale="ka">
        <ProductDetailPage slug="optika" detail={detail} />
      </TestProviders>,
    );
    expect(
      screen.getByRole("button", { name: "კალათა მალე დაემატება" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("რაოდენობა")).toBeInTheDocument();
  });

  it("renders a product 404 state", () => {
    render(
      <TestProviders>
        <ProductDetailPage
          slug="missing"
          error={{ code: "CATALOG_PRODUCT_NOT_FOUND", message: "Gone" }}
        />
      </TestProviders>,
    );
    expect(
      screen.getByText(
        /this product is not available|ეს პროდუქტი ხელმისაწვდომი არ არის/i,
      ),
    ).toBeInTheDocument();
  });

  it("has a single h1 and no axe violations on the default variant", async () => {
    const { container } = render(
      <TestProviders>
        <ProductDetailPage slug="optika" detail={detail} />
      </TestProviders>,
    );
    expect(screen.getAllByRole("heading", { level: 1 })).toHaveLength(1);
    expect(await axe(container)).toHaveNoViolations();
  });
});
