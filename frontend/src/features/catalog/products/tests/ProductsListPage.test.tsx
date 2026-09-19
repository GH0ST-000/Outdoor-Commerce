import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProductsListPage } from "@/features/catalog/products/components/ProductsListPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchAdminProducts = vi.fn();
const fetchCatalogBrandOptions = vi.fn();
const fetchCatalogCategoryOptions = vi.fn();
const archiveAdminProduct = vi.fn();
const restoreAdminProduct = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/catalog/products",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/catalog/products/api/products-api", () => ({
  fetchAdminProducts: (...args: unknown[]) => fetchAdminProducts(...args),
  fetchCatalogBrandOptions: (...args: unknown[]) =>
    fetchCatalogBrandOptions(...args),
  fetchCatalogCategoryOptions: (...args: unknown[]) =>
    fetchCatalogCategoryOptions(...args),
  archiveAdminProduct: (...args: unknown[]) => archiveAdminProduct(...args),
  restoreAdminProduct: (...args: unknown[]) => restoreAdminProduct(...args),
}));

function renderList() {
  return render(
    <TestProviders>
      <ProductsListPage />
    </TestProviders>,
  );
}

describe("ProductsListPage", () => {
  beforeEach(() => {
    fetchAdminProducts.mockReset();
    fetchCatalogBrandOptions.mockReset();
    fetchCatalogCategoryOptions.mockReset();
    archiveAdminProduct.mockReset();
    restoreAdminProduct.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();

    fetchCatalogBrandOptions.mockResolvedValue([]);
    fetchCatalogCategoryOptions.mockResolvedValue([]);
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE],
      roles: ["catalog-manager"],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });
  });

  it("shows a loading state then rows with readiness", async () => {
    let resolveProducts: (value: unknown) => void = () => undefined;
    fetchAdminProducts.mockReturnValue(
      new Promise((resolve) => {
        resolveProducts = resolve;
      }),
    );

    renderList();
    expect(screen.getByRole("status")).toHaveTextContent("Loading products");

    resolveProducts({
      data: [
        {
          id: 4,
          name: "ოპტიკა",
          slug: "optika",
          status: "draft",
          brand: { id: 1, name: "Demo Brand" },
          primary_category: { id: 2, name: "Optics" },
          category_count: 1,
          model_number: "X1",
          is_featured: false,
          readiness: { ready: false, issue_count: 2 },
          published_at: null,
          created_at: "2026-01-01T00:00:00+00:00",
          updated_at: "2026-01-01T00:00:00+00:00",
          deleted_at: null,
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

    expect(await screen.findByText("ოპტიკა")).toBeInTheDocument();
    expect(screen.getByTestId("readiness")).toHaveTextContent("2 issues");
    expect(screen.getByRole("link", { name: "ოპტიკა" })).toHaveAttribute(
      "href",
      "/admin/catalog/products/4/edit",
    );
    expect(screen.getByRole("button", { name: "Archive" })).toBeInTheDocument();
  });

  it("shows an empty state when no products match", async () => {
    fetchAdminProducts.mockResolvedValue({
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

    renderList();
    expect(
      await screen.findByText("No products match these filters."),
    ).toBeInTheDocument();
  });

  it("shows an error state when the API fails", async () => {
    const { ApiClientError } = await import("@/lib/api-client");
    fetchAdminProducts.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "PERMISSION_DENIED",
        message: "Permission denied.",
      }),
    );

    renderList();
    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("Permission denied.");
    });
  });

  it("hides manage actions without catalog.manage", async () => {
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.CATALOG_VIEW],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });

    fetchAdminProducts.mockResolvedValue({
      data: [
        {
          id: 4,
          name: "Scope",
          slug: "scope",
          status: "draft",
          brand: null,
          primary_category: null,
          category_count: 0,
          model_number: null,
          is_featured: false,
          readiness: { ready: true, issue_count: 0 },
          published_at: null,
          created_at: null,
          updated_at: null,
          deleted_at: null,
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

    renderList();
    expect(await screen.findByText("Scope")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "New product" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Archive" })).toBeNull();
    expect(screen.getByTestId("readiness")).toHaveTextContent("Ready");
  });
});
