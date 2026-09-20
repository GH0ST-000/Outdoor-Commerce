import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { InventoryDetailPage } from "@/features/inventory/components/InventoryDetailPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { TestProviders } from "@/test/providers";

const fetchAdminInventoryBalance = vi.fn();
const fetchAdminInventoryLedger = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/inventory/api/inventory-api", () => ({
  fetchAdminInventoryBalance: (...args: unknown[]) =>
    fetchAdminInventoryBalance(...args),
  fetchAdminInventoryLedger: (...args: unknown[]) =>
    fetchAdminInventoryLedger(...args),
}));

const balance = {
  warehouse: { id: 1, code: "WH", name: "Main" },
  product: { id: 10, name: "Scope" },
  variant: {
    id: 20,
    sku: "SKU-1",
    barcode: null,
    combination_label: null,
  },
  quantities: {
    on_hand: 8,
    reserved: 0,
    unreserved: 8,
    safety_stock: 1,
    available_to_sell: 7,
    reorder_point: 2,
  },
  status: { low_stock: false, out_of_stock: false },
  version: 1,
  last_movement_at: null,
};

describe("InventoryDetailPage permissions", () => {
  beforeEach(() => {
    fetchAdminInventoryBalance.mockReset();
    fetchAdminInventoryLedger.mockReset();
    fetchAdminInventoryBalance.mockResolvedValue(balance);
    fetchAdminInventoryLedger.mockResolvedValue({
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
  });

  it("hides stock-changing actions without adjust/transfer/manage", async () => {
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.INVENTORY_VIEW],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });

    render(
      <TestProviders>
        <InventoryDetailPage warehouseId="1" variantId="20" />
      </TestProviders>,
    );

    await waitFor(() => {
      expect(screen.getByText("Scope")).toBeInTheDocument();
    });

    expect(screen.queryByRole("button", { name: "Receive" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Transfer" })).toBeNull();
    expect(screen.queryByRole("button", { name: "Settings" })).toBeNull();
  });

  it("shows actions when grants include adjust, transfer, and manage", async () => {
    useAdminContextMock.mockReturnValue({
      permissions: [
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_ADJUST,
        PERMISSIONS.INVENTORY_TRANSFER,
        PERMISSIONS.INVENTORY_MANAGE,
      ],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });

    render(
      <TestProviders>
        <InventoryDetailPage warehouseId="1" variantId="20" />
      </TestProviders>,
    );

    await waitFor(() => {
      expect(
        screen.getByRole("button", { name: "Receive" }),
      ).toBeInTheDocument();
    });
    expect(
      screen.getByRole("button", { name: "Transfer" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Settings" }),
    ).toBeInTheDocument();
  });
});
