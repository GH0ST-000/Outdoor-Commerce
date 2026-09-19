import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProductFormPage } from "@/features/catalog/products/components/ProductFormPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const createAdminProduct = vi.fn();
const updateAdminProduct = vi.fn();
const fetchAdminProduct = vi.fn();
const fetchCatalogBrandOptions = vi.fn();
const fetchCatalogCategoryOptions = vi.fn();
const archiveAdminProduct = vi.fn();
const fetchProductVariantAxes = vi.fn();
const fetchProductVariants = vi.fn();
const fetchAdminAttributes = vi.fn();
const fetchAdminAttributeValues = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/catalog/products/new",
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/catalog/products/api/products-api", () => ({
  createAdminProduct: (...args: unknown[]) => createAdminProduct(...args),
  updateAdminProduct: (...args: unknown[]) => updateAdminProduct(...args),
  fetchAdminProduct: (...args: unknown[]) => fetchAdminProduct(...args),
  fetchCatalogBrandOptions: (...args: unknown[]) =>
    fetchCatalogBrandOptions(...args),
  fetchCatalogCategoryOptions: (...args: unknown[]) =>
    fetchCatalogCategoryOptions(...args),
  archiveAdminProduct: (...args: unknown[]) => archiveAdminProduct(...args),
}));

vi.mock("@/features/catalog/variants/api/variants-api", () => ({
  fetchProductVariantAxes: (...args: unknown[]) =>
    fetchProductVariantAxes(...args),
  saveProductVariantAxes: vi.fn(),
  fetchProductVariants: (...args: unknown[]) => fetchProductVariants(...args),
  setDefaultProductVariant: vi.fn(),
  archiveProductVariant: vi.fn(),
  restoreProductVariant: vi.fn(),
  previewProductVariantGeneration: vi.fn(),
  generateProductVariants: vi.fn(),
  createProductVariant: vi.fn(),
  updateProductVariant: vi.fn(),
}));

vi.mock("@/features/catalog/attributes/api/attributes-api", () => ({
  fetchAdminAttributes: (...args: unknown[]) => fetchAdminAttributes(...args),
  fetchAdminAttributeValues: (...args: unknown[]) =>
    fetchAdminAttributeValues(...args),
}));

function renderCreate(permissions: string[]) {
  useAdminContextMock.mockReturnValue({
    permissions,
    roles: [],
    context: null,
    status: "ready",
    error: null,
    load: vi.fn(),
    refresh: vi.fn(),
  });

  return render(
    <TestProviders>
      <ProductFormPage mode="create" />
    </TestProviders>,
  );
}

function renderEdit(permissions: string[]) {
  useAdminContextMock.mockReturnValue({
    permissions,
    roles: [],
    context: null,
    status: "ready",
    error: null,
    load: vi.fn(),
    refresh: vi.fn(),
  });

  return render(
    <TestProviders>
      <ProductFormPage mode="edit" productId="7" />
    </TestProviders>,
  );
}

async function openStatusOptions(user: ReturnType<typeof userEvent.setup>) {
  await user.click(screen.getByRole("tab", { name: "Publish" }));
  const status = await screen.findByLabelText("Status");
  await user.click(status);
  const listbox = await screen.findByRole("listbox");
  return within(listbox)
    .getAllByRole("option")
    .map((option) => option.getAttribute("data-value") ?? option.textContent);
}

describe("ProductFormPage", () => {
  beforeEach(() => {
    createAdminProduct.mockReset();
    updateAdminProduct.mockReset();
    fetchAdminProduct.mockReset();
    fetchCatalogBrandOptions.mockReset();
    fetchCatalogCategoryOptions.mockReset();
    archiveAdminProduct.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();

    fetchCatalogBrandOptions.mockResolvedValue([
      { id: 1, name: "Demo Brand", status: "active" },
    ]);
    fetchCatalogCategoryOptions.mockResolvedValue([
      { id: 2, name: "Optics", status: "active", parent_id: null },
    ]);

    const emptyPage = {
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
        from: null,
        to: null,
      },
    };
    fetchProductVariantAxes.mockReset();
    fetchProductVariants.mockReset();
    fetchAdminAttributes.mockReset();
    fetchAdminAttributeValues.mockReset();
    fetchProductVariantAxes.mockResolvedValue({ product_id: 7, axes: [] });
    fetchProductVariants.mockResolvedValue(emptyPage);
    fetchAdminAttributes.mockResolvedValue(emptyPage);
    fetchAdminAttributeValues.mockResolvedValue(emptyPage);
  });

  it("requires Georgian name before submit", async () => {
    const user = userEvent.setup();
    renderCreate([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    await screen.findByLabelText("Model number");
    await user.click(screen.getByRole("button", { name: "Create product" }));

    expect(await screen.findByText("Name is required.")).toBeInTheDocument();
    expect(createAdminProduct).not.toHaveBeenCalled();
  });

  it("hides activate status without catalog.publish", async () => {
    const user = userEvent.setup();
    renderCreate([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    const values = await openStatusOptions(user);
    expect(
      screen.getByText("Activate is hidden without catalog.publish."),
    ).toBeInTheDocument();
    expect(values).toEqual(["draft"]);
  });

  it("shows activate when catalog.publish is granted", async () => {
    const user = userEvent.setup();
    renderCreate([
      PERMISSIONS.CATALOG_VIEW,
      PERMISSIONS.CATALOG_MANAGE,
      PERMISSIONS.CATALOG_PUBLISH,
    ]);

    const values = await openStatusOptions(user);
    expect(values).toEqual(["draft", "active"]);
  });

  it("displays readiness issues on edit", async () => {
    const user = userEvent.setup();
    fetchAdminProduct.mockResolvedValue({
      id: 7,
      status: "draft",
      brand_id: null,
      brand: null,
      primary_category_id: null,
      primary_category: null,
      categories: [],
      model_number: null,
      manufacturer_part_number: null,
      is_featured: false,
      sort_order: 0,
      published_at: null,
      translations: [
        {
          locale: "ka",
          name: "ტესტი",
          slug: "testi",
          short_description: null,
          description: null,
          seo_title: null,
          seo_description: null,
        },
      ],
      readiness: {
        ready: false,
        issue_count: 1,
        issues: {
          primary_category_id: ["An active primary category is required."],
        },
      },
      created_by: 1,
      updated_by: 1,
      created_at: null,
      updated_at: null,
      deleted_at: null,
    });

    renderEdit([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    await screen.findByLabelText("Model number");
    await user.click(screen.getByRole("tab", { name: "Publish" }));

    expect(await screen.findByTestId("readiness")).toHaveTextContent(
      "Not ready · 1 issue",
    );
    expect(
      screen.getByText(/An active primary category is required/),
    ).toBeInTheDocument();
  });

  it("asks to save the product before configuring variants on create", async () => {
    const user = userEvent.setup();
    renderCreate([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    await user.click(await screen.findByRole("tab", { name: "Variants" }));

    expect(
      await screen.findByText(
        "Save the product first, then configure variants.",
      ),
    ).toBeInTheDocument();
    expect(fetchProductVariantAxes).not.toHaveBeenCalled();
  });

  it("loads the variants workspace on edit", async () => {
    const user = userEvent.setup();
    fetchAdminProduct.mockResolvedValue({
      id: 7,
      status: "draft",
      brand_id: null,
      brand: null,
      primary_category_id: null,
      primary_category: null,
      categories: [],
      model_number: null,
      manufacturer_part_number: null,
      is_featured: false,
      sort_order: 0,
      published_at: null,
      translations: [
        {
          locale: "ka",
          name: "ტესტი",
          slug: "testi",
          short_description: null,
          description: null,
          seo_title: null,
          seo_description: null,
        },
      ],
      readiness: { ready: true, issue_count: 0, issues: {} },
      created_by: 1,
      updated_by: 1,
      created_at: null,
      updated_at: null,
      deleted_at: null,
    });

    renderEdit([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    await screen.findByLabelText("Model number");
    await user.click(screen.getByRole("tab", { name: "Variants" }));

    expect(await screen.findByText("No variants yet.")).toBeInTheDocument();
    expect(fetchProductVariantAxes).toHaveBeenCalledWith("7");
    expect(
      screen.getByText(
        "No active attributes are available yet. Create and activate attributes first.",
      ),
    ).toBeInTheDocument();
  });

  it("preserves Georgian values when switching locale tabs", async () => {
    const user = userEvent.setup();
    renderCreate([PERMISSIONS.CATALOG_VIEW, PERMISSIONS.CATALOG_MANAGE]);

    await user.click(screen.getByRole("tab", { name: "Content" }));
    const nameInput = await screen.findByLabelText("Name");
    await user.type(nameInput, "სახელი");
    await user.click(screen.getByRole("tab", { name: /English/ }));
    await user.click(screen.getByRole("tab", { name: /Georgian/ }));

    await waitFor(() => {
      expect(screen.getByLabelText("Name")).toHaveValue("სახელი");
    });
  });
});
