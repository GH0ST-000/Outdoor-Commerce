import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProductVariantsSection } from "@/features/catalog/variants/components/ProductVariantsSection";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchProductVariantAxes = vi.fn();
const saveProductVariantAxes = vi.fn();
const fetchProductVariants = vi.fn();
const setDefaultProductVariant = vi.fn();
const archiveProductVariant = vi.fn();
const restoreProductVariant = vi.fn();
const previewProductVariantGeneration = vi.fn();
const generateProductVariants = vi.fn();
const createProductVariant = vi.fn();
const updateProductVariant = vi.fn();
const fetchAdminAttributes = vi.fn();
const fetchAdminAttributeValues = vi.fn();

vi.mock("@/features/catalog/variants/api/variants-api", () => ({
  fetchProductVariantAxes: (...args: unknown[]) =>
    fetchProductVariantAxes(...args),
  saveProductVariantAxes: (...args: unknown[]) =>
    saveProductVariantAxes(...args),
  fetchProductVariants: (...args: unknown[]) => fetchProductVariants(...args),
  setDefaultProductVariant: (...args: unknown[]) =>
    setDefaultProductVariant(...args),
  archiveProductVariant: (...args: unknown[]) => archiveProductVariant(...args),
  restoreProductVariant: (...args: unknown[]) => restoreProductVariant(...args),
  previewProductVariantGeneration: (...args: unknown[]) =>
    previewProductVariantGeneration(...args),
  generateProductVariants: (...args: unknown[]) =>
    generateProductVariants(...args),
  createProductVariant: (...args: unknown[]) => createProductVariant(...args),
  updateProductVariant: (...args: unknown[]) => updateProductVariant(...args),
}));

vi.mock("@/features/catalog/attributes/api/attributes-api", () => ({
  fetchAdminAttributes: (...args: unknown[]) => fetchAdminAttributes(...args),
  fetchAdminAttributeValues: (...args: unknown[]) =>
    fetchAdminAttributeValues(...args),
}));

vi.mock("@/features/catalog/media/components/VariantMediaPanel", () => ({
  VariantMediaPanel: () => (
    <div data-testid="variant-media-panel">Variant media panel</div>
  ),
}));

const pagination = {
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 1,
  from: 1,
  to: 1,
};

function renderSection(canManage = true) {
  return render(
    <TestProviders>
      <ProductVariantsSection
        productId="7"
        canManage={canManage}
        canPublish={false}
      />
    </TestProviders>,
  );
}

describe("ProductVariantsSection", () => {
  beforeEach(() => {
    vi.resetAllMocks();
    resetApiClientStateForTests();

    fetchProductVariantAxes.mockResolvedValue({
      product_id: 7,
      axes: [
        {
          attribute_id: 1,
          code: "color",
          name: "Color",
          type: "color",
          status: "active",
          sort_order: 0,
        },
      ],
    });
    fetchAdminAttributes.mockResolvedValue({
      data: [
        {
          id: 1,
          code: "color",
          name: "Color",
          type: "color",
          status: "active",
          is_filterable: true,
          sort_order: 0,
          value_count: 2,
          created_at: null,
          updated_at: null,
          deleted_at: null,
        },
      ],
      meta: pagination,
    });
    fetchAdminAttributeValues.mockResolvedValue({
      data: [],
      meta: { ...pagination, total: 0, from: null, to: null },
    });
    fetchProductVariants.mockResolvedValue({
      data: [
        {
          id: 21,
          product_id: 7,
          sku: "HUNT-7-0001",
          barcode: null,
          status: "draft",
          is_default: true,
          sort_order: 0,
          combination_signature: "color:red",
          attribute_values: [],
          created_at: null,
          updated_at: null,
          deleted_at: null,
        },
      ],
      meta: pagination,
    });
  });

  it("loads axes and variants for the product", async () => {
    renderSection();

    expect(await screen.findByText("HUNT-7-0001")).toBeInTheDocument();
    expect(screen.getByTestId("axes-order")).toHaveTextContent("1. Color");
    expect(fetchProductVariantAxes).toHaveBeenCalledWith("7");
    expect(screen.getByRole("button", { name: "New variant" })).toBeVisible();
  });

  it("hides manage actions without catalog.manage", async () => {
    renderSection(false);

    expect(await screen.findByText("HUNT-7-0001")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "New variant" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Save axes" })).toBeNull();
    expect(
      screen.getByText("You need catalog.manage to change variant axes."),
    ).toBeInTheDocument();
  });

  it("prompts for a replacement when archiving the default variant", async () => {
    const user = userEvent.setup();
    renderSection();

    await user.click(await screen.findByRole("button", { name: "Archive" }));

    expect(screen.getByRole("alertdialog")).toHaveTextContent(
      "Archive HUNT-7-0001?",
    );
    expect(screen.getByLabelText("Replacement default")).toBeInTheDocument();
  });
});
