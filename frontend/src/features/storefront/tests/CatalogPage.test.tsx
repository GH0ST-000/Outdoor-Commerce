import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { CatalogPage } from "@/features/storefront/components/CatalogPage";
import { TestProviders } from "@/test/providers";
import type { CatalogListState } from "@/features/catalog/lib/load-public-catalog";
import { emptyCatalogQuery } from "@/features/catalog/query-state/catalog-search-params";

const { push, refresh } = vi.hoisted(() => ({
  push: vi.fn(),
  refresh: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  usePathname: () => "/catalog",
  useRouter: () => ({ push, replace: vi.fn(), refresh }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const emptyState: CatalogListState = {
  products: [],
  total: 0,
  page: 1,
  perPage: 10,
  lastPage: 1,
  sort: "featured",
  facets: {
    brands: [{ id: 1, code: "condor", name: "Condor", count: 1 }],
    categories: [],
    attributes: [
      {
        code: "color",
        name: "Color",
        values: [{ code: "black", name: "Black", count: 1 }],
      },
    ],
    price_range: {
      currency: "GEL",
      min_final_amount_minor: 1000,
      max_final_amount_minor: 5000,
    },
    in_stock_count: 1,
    on_sale_count: 0,
  },
  categories: [],
  title: "Catalog",
  lead: "",
  error: null,
};

const pricedProduct = {
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

describe("CatalogPage URL state", () => {
  beforeEach(() => {
    push.mockReset();
    refresh.mockReset();
  });

  it("renders priced cards from initial server data", () => {
    render(
      <TestProviders>
        <CatalogPage
          pathname="/catalog"
          query={emptyCatalogQuery()}
          initial={{
            ...emptyState,
            products: [pricedProduct],
            total: 1,
          }}
        />
      </TestProviders>,
    );

    expect(screen.getByText("Optics")).toBeInTheDocument();
    expect(screen.getByTestId("price-display-range")).toBeInTheDocument();
    expect(screen.getAllByText(/in stock|მარაგშია/i).length).toBeGreaterThan(0);
  });

  it("shows empty and error states", () => {
    const { rerender } = render(
      <TestProviders>
        <CatalogPage
          pathname="/catalog"
          query={emptyCatalogQuery()}
          initial={emptyState}
        />
      </TestProviders>,
    );
    expect(
      screen.getByText(/no products in this category|ამ კატეგორიაში პროდუქტი/i),
    ).toBeInTheDocument();

    rerender(
      <TestProviders>
        <CatalogPage
          pathname="/catalog"
          query={emptyCatalogQuery()}
          initial={{
            ...emptyState,
            error: { code: "CATALOG_RATE_LIMITED", message: "Slow down" },
          }}
        />
      </TestProviders>,
    );
    expect(
      screen.getByText(/too many catalog requests|ძალიან ბევრი მოთხოვნა/i),
    ).toBeInTheDocument();
  });

  it("pushes brand, in-stock, and sort into the URL and resets page", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <CatalogPage
          pathname="/catalog/hunting"
          query={{ ...emptyCatalogQuery(), page: 2 }}
          initial={{ ...emptyState, total: 12, lastPage: 3 }}
        />
      </TestProviders>,
    );

    await user.click(screen.getByRole("checkbox", { name: /Condor/ }));
    expect(push).toHaveBeenCalled();
    expect(String(push.mock.calls[0]?.[0])).toContain("brand=condor");
    expect(String(push.mock.calls[0]?.[0])).not.toContain("page=2");

    await user.click(
      screen.getByRole("checkbox", { name: /in stock|მარაგშია/i }),
    );
    expect(
      push.mock.calls.some((call) => String(call[0]).includes("in_stock=1")),
    ).toBe(true);

    await user.click(screen.getByRole("combobox", { name: /sort|დალაგება/i }));
    await user.click(
      screen.getByRole("option", { name: /price: low|ფასი: იაფი/i }),
    );
    expect(
      push.mock.calls.some((call) =>
        String(call[0]).includes("sort=price_asc"),
      ),
    ).toBe(true);
  });

  it("shows a filter empty state and clears back to the base path", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <CatalogPage
          pathname="/catalog"
          query={{ ...emptyCatalogQuery(), brand: ["condor"] }}
          initial={{ ...emptyState, total: 0 }}
        />
      </TestProviders>,
    );
    expect(
      screen.getByText(/no products match|ფილტრებს პროდუქტი/i),
    ).toBeInTheDocument();
    await user.click(
      screen.getAllByRole("button", {
        name: /clear filters|ფილტრების გასუფთავება/i,
      })[0]!,
    );
    expect(push).toHaveBeenCalledWith("/catalog", { scroll: false });
  });

  it("has accessible filter labels and no axe violations", async () => {
    const { container } = render(
      <TestProviders>
        <CatalogPage
          pathname="/catalog"
          query={emptyCatalogQuery()}
          initial={{
            ...emptyState,
            products: [pricedProduct],
            total: 1,
          }}
        />
      </TestProviders>,
    );
    expect(
      screen.getByRole("checkbox", { name: /Condor/ }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("combobox", { name: /sort|დალაგება/i }),
    ).toBeInTheDocument();
    const results = await axe(container);
    expect(results).toHaveNoViolations();
  });

  it("keeps the search query when filters are cleared", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <CatalogPage
          pathname="/search"
          query={{
            ...emptyCatalogQuery(),
            q: "scope",
            brand: ["condor"],
            sort: "default",
          }}
          initial={{ ...emptyState, total: 1, products: [pricedProduct] }}
        />
      </TestProviders>,
    );

    await user.click(
      screen.getAllByRole("button", { name: /clear filters/i })[0]!,
    );
    expect(push).toHaveBeenCalled();
    const href = String(push.mock.calls.at(-1)?.[0]);
    expect(href).toContain("/search?");
    expect(href).toContain("q=scope");
    expect(href).not.toContain("brand=");
  });
});
