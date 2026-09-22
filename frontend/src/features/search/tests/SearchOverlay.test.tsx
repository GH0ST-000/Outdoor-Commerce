import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { SearchOverlay } from "@/features/search/components/SearchOverlay";
import { TestProviders } from "@/test/providers";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/",
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const suggestions = vi.hoisted(() => vi.fn());

vi.mock("@/features/catalog/api/public-catalog-client", () => ({
  getSearchSuggestions: (...args: unknown[]) => suggestions(...args),
}));

const productCard = {
  id: 12,
  name: "Alpine Scope",
  slug: "alpine-scope",
  href: "/products/alpine-scope",
  brand: { id: 1, name: "Ridge", slug: "ridge" },
  primary_category: null,
  primary_media: null,
  price: {
    currency: "GEL",
    min_final_amount_minor: 10000,
    max_final_amount_minor: 10000,
    min_base_amount_minor: 10000,
    is_range: false,
    on_sale: false,
  },
  availability: {
    status: "in_stock" as const,
    purchasable: true,
    low_stock: false,
  },
  is_featured: false,
  variant_count: 1,
  default_variant_id: 12,
};

describe("SearchOverlay", () => {
  beforeEach(() => {
    push.mockReset();
    suggestions.mockReset();
    suggestions.mockResolvedValue({
      data: {
        query: "scope",
        products: [productCard],
        categories: [
          { id: 2, name: "Optics", slug: "optics", path: "/catalog/optics" },
        ],
        brands: [
          { id: 1, name: "Ridge", slug: "ridge", path: "/brands/ridge" },
        ],
      },
      meta: { fallback_used: false },
    });
  });

  it("opens focused, debounces, and navigates with keyboard", async () => {
    const user = userEvent.setup();
    const inputRef = { current: null as HTMLInputElement | null };
    const onClose = vi.fn();
    render(
      <TestProviders>
        <SearchOverlay open onClose={onClose} inputRef={inputRef} />
      </TestProviders>,
    );

    const input = screen.getByRole("combobox", { name: /search/i });
    expect(input).toBeInTheDocument();

    await user.type(input, "scope");
    expect(suggestions).not.toHaveBeenCalled();
    await waitFor(() => expect(suggestions).toHaveBeenCalledTimes(1), {
      timeout: 1500,
    });

    await user.keyboard("{ArrowDown}");
    await user.keyboard("{Enter}");
    expect(onClose).toHaveBeenCalled();
    expect(push).toHaveBeenCalled();
  });

  it("ignores stale responses and shows the empty state", async () => {
    const user = userEvent.setup();
    let resolveFirst: ((value: unknown) => void) | undefined;
    let calls = 0;
    suggestions.mockImplementation((input: { q: string }) => {
      calls += 1;
      if (calls === 1) {
        return new Promise((resolve) => {
          resolveFirst = resolve;
        });
      }
      if (input.q === "sc") {
        return Promise.resolve({
          data: { query: "sc", products: [], categories: [], brands: [] },
          meta: { fallback_used: false },
        });
      }
      return Promise.resolve({
        data: {
          query: input.q,
          products: [productCard],
          categories: [],
          brands: [],
        },
        meta: { fallback_used: false },
      });
    });

    render(
      <TestProviders>
        <SearchOverlay open onClose={vi.fn()} inputRef={{ current: null }} />
      </TestProviders>,
    );

    const input = screen.getByRole("combobox");
    await user.type(input, "scope");
    await waitFor(() => expect(suggestions).toHaveBeenCalled(), {
      timeout: 1500,
    });
    await user.clear(input);
    await user.type(input, "sc");
    resolveFirst?.({
      data: {
        query: "scope",
        products: [productCard],
        categories: [],
        brands: [],
      },
      meta: { fallback_used: false },
    });

    await waitFor(
      () =>
        expect(
          screen.getAllByText(/no matching|check the spelling/i).length,
        ).toBeGreaterThan(0),
      { timeout: 2500 },
    );
    expect(screen.queryByText("Alpine Scope")).not.toBeInTheDocument();
  });

  it("closes on escape from the header handler path via the close button", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    render(
      <TestProviders>
        <SearchOverlay open onClose={onClose} inputRef={{ current: null }} />
      </TestProviders>,
    );
    await user.click(screen.getByRole("button", { name: /close search/i }));
    expect(onClose).toHaveBeenCalled();
  });

  it("renders query HTML as text, not markup", async () => {
    const user = userEvent.setup();
    suggestions.mockResolvedValue({
      data: {
        query: "<script>alert(1)</script>",
        products: [
          {
            ...productCard,
            name: "<script>alert(1)</script> Scope",
          },
        ],
        categories: [],
        brands: [],
      },
      meta: { fallback_used: false },
    });
    render(
      <TestProviders>
        <SearchOverlay open onClose={vi.fn()} inputRef={{ current: null }} />
      </TestProviders>,
    );
    await user.type(screen.getByRole("combobox"), "<script>");
    await waitFor(
      () => {
        expect(document.body.textContent ?? "").toContain("<script>");
      },
      { timeout: 2500 },
    );
    expect(document.querySelector("script")).toBeNull();
  });
});
