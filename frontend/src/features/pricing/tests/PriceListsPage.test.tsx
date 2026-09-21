import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PriceListsPage } from "@/features/pricing/components/PriceListsPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { ApiClientError, resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchAdminPriceLists = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/pricing/price-lists",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/pricing/api/price-lists-api", () => ({
  fetchAdminPriceLists: (...args: unknown[]) => fetchAdminPriceLists(...args),
  archiveAdminPriceList: vi.fn(),
  restoreAdminPriceList: vi.fn(),
  setAdminPriceListDefault: vi.fn(),
  updateAdminPriceListStatus: vi.fn(),
}));

function renderPage() {
  return render(
    <TestProviders>
      <PriceListsPage />
    </TestProviders>,
  );
}

describe("PriceListsPage", () => {
  beforeEach(() => {
    fetchAdminPriceLists.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.PRICING_VIEW],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });
  });

  it("requests pagination with default per_page 10", async () => {
    fetchAdminPriceLists.mockResolvedValue({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
      },
    });

    renderPage();
    await screen.findByText("No price lists match these filters.");
    expect(fetchAdminPriceLists).toHaveBeenCalledWith(
      expect.objectContaining({ per_page: 10, page: 1 }),
    );
  });

  it("shows loading then price list rows", async () => {
    let resolveLists: (value: unknown) => void = () => undefined;
    fetchAdminPriceLists.mockReturnValue(
      new Promise((resolve) => {
        resolveLists = resolve;
      }),
    );

    renderPage();
    expect(screen.getByRole("status")).toHaveTextContent("Loading price lists");

    resolveLists({
      data: [
        {
          id: 1,
          code: "retail-gel",
          name: "Retail GEL",
          currency_code: "GEL",
          status: "active",
          is_default: true,
          priority: 0,
          prices_include_tax: true,
          priced_variant_count: 12,
          created_at: "2026-01-01T00:00:00Z",
          updated_at: "2026-01-01T00:00:00Z",
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    expect(await screen.findByText("Retail GEL")).toBeInTheDocument();
  });

  it("shows empty state when no rows match", async () => {
    fetchAdminPriceLists.mockResolvedValue({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
      },
    });

    renderPage();
    expect(
      await screen.findByText("No price lists match these filters."),
    ).toBeInTheDocument();
  });

  it("shows API error message", async () => {
    fetchAdminPriceLists.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "FORBIDDEN",
        message: "Forbidden",
      }),
    );

    renderPage();
    expect(await screen.findByRole("alert")).toHaveTextContent("Forbidden");
  });

  it("hides manage actions without pricing.manage", async () => {
    fetchAdminPriceLists.mockResolvedValue({
      data: [
        {
          id: 2,
          code: "b2b",
          name: "B2B",
          currency_code: "GEL",
          status: "draft",
          is_default: false,
          priority: 1,
          prices_include_tax: false,
          created_at: "2026-01-01T00:00:00Z",
          updated_at: "2026-01-01T00:00:00Z",
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    renderPage();
    await screen.findByText("B2B");
    expect(screen.queryByText("New price list")).not.toBeInTheDocument();
    expect(screen.queryByText("Archive")).not.toBeInTheDocument();
  });

  it("shows manage actions with pricing.manage", async () => {
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.PRICING_VIEW, PERMISSIONS.PRICING_MANAGE],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });

    fetchAdminPriceLists.mockResolvedValue({
      data: [
        {
          id: 3,
          code: "outlet",
          name: "Outlet",
          currency_code: "GEL",
          status: "draft",
          is_default: false,
          priority: 2,
          prices_include_tax: true,
          created_at: "2026-01-01T00:00:00Z",
          updated_at: "2026-01-01T00:00:00Z",
        },
      ],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 1,
        from: 1,
        to: 1,
      },
    });

    renderPage();
    expect(await screen.findByText("New price list")).toBeInTheDocument();
    expect(screen.getByText("Archive")).toBeInTheDocument();
  });
});
