import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { VariantsTable } from "@/features/catalog/variants/components/VariantsTable";
import type { ProductVariantListItem } from "@/features/catalog/variants/types/variant-types";
import { TestProviders } from "@/test/providers";

function variant(
  overrides: Partial<ProductVariantListItem> = {},
): ProductVariantListItem {
  return {
    id: 21,
    product_id: 7,
    sku: "HUNT-7-0001",
    barcode: "1234567890",
    status: "draft",
    is_default: true,
    sort_order: 0,
    combination_signature: "color:red",
    attribute_values: [
      {
        attribute_id: 1,
        attribute_code: "color",
        attribute_name: "Color",
        attribute_value_id: 11,
        attribute_value_code: "red",
        attribute_value_name: "Red",
        color_hex: "#FF0000",
      },
    ],
    created_at: null,
    updated_at: null,
    deleted_at: null,
    ...overrides,
  };
}

function renderTable(
  props: Partial<React.ComponentProps<typeof VariantsTable>> = {},
) {
  const handlers = {
    onEdit: vi.fn(),
    onSetDefault: vi.fn(),
    onArchive: vi.fn(),
    onRestore: vi.fn(),
  };

  render(
    <TestProviders>
      <VariantsTable
        variants={[variant()]}
        error={null}
        canManage
        pending={false}
        {...handlers}
        {...props}
      />
    </TestProviders>,
  );

  return handlers;
}

describe("VariantsTable", () => {
  it("shows a loading state while variants are unknown", () => {
    renderTable({ variants: null });
    expect(screen.getByRole("status")).toHaveTextContent("Loading variants");
  });

  it("shows an empty state when the product has no variants", () => {
    renderTable({ variants: [] });
    expect(screen.getByRole("status")).toHaveTextContent("No variants yet.");
  });

  it("shows an error state", () => {
    renderTable({ error: "Permission denied." });
    expect(screen.getByRole("alert")).toHaveTextContent("Permission denied.");
  });

  it("renders SKU, barcode, combination, and the default badge", () => {
    renderTable();
    expect(screen.getByText("HUNT-7-0001")).toBeInTheDocument();
    expect(screen.getByText("1234567890")).toBeInTheDocument();
    expect(screen.getByText("Red")).toBeInTheDocument();
    expect(screen.getByText("Default")).toBeInTheDocument();
    expect(screen.getByRole("img", { name: "Color #FF0000" })).toBeVisible();
    expect(screen.queryByRole("button", { name: "Set default" })).toBeNull();
  });

  it("archives through the parent handler", async () => {
    const user = userEvent.setup();
    const handlers = renderTable({
      variants: [variant({ is_default: false })],
    });

    await user.click(screen.getByRole("button", { name: "Set default" }));
    await user.click(screen.getByRole("button", { name: "Archive" }));

    expect(handlers.onSetDefault).toHaveBeenCalledTimes(1);
    expect(handlers.onArchive).toHaveBeenCalledTimes(1);
  });

  it("hides manage actions without catalog.manage", () => {
    renderTable({ canManage: false });
    expect(screen.queryByRole("button", { name: "Edit" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Archive" })).toBeNull();
    expect(screen.getByText("HUNT-7-0001")).toBeInTheDocument();
  });
});
