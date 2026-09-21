import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { InventoryListPage } from "@/features/inventory/components/InventoryListPage";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { resetApiClientStateForTests } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const fetchAdminInventory = vi.fn();
const fetchAdminWarehouses = vi.fn();
const replaceMock = vi.fn();
const useAdminContextMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock, push: vi.fn() }),
  usePathname: () => "/admin/inventory",
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("@/features/admin/hooks/use-admin-context", () => ({
  useAdminContext: () => useAdminContextMock(),
}));

vi.mock("@/features/inventory/api/inventory-api", () => ({
  fetchAdminInventory: (...args: unknown[]) => fetchAdminInventory(...args),
}));

vi.mock("@/features/inventory/api/warehouses-api", () => ({
  fetchAdminWarehouses: (...args: unknown[]) => fetchAdminWarehouses(...args),
}));

function renderList() {
  return render(
    <TestProviders>
      <InventoryListPage />
    </TestProviders>,
  );
}

describe("InventoryListPage", () => {
  beforeEach(() => {
    fetchAdminInventory.mockReset();
    fetchAdminWarehouses.mockReset();
    replaceMock.mockReset();
    resetApiClientStateForTests();
    fetchAdminWarehouses.mockResolvedValue({ data: [], meta: { total: 0 } });
    useAdminContextMock.mockReturnValue({
      permissions: [PERMISSIONS.INVENTORY_VIEW],
      roles: [],
      context: null,
      status: "ready",
      error: null,
      load: vi.fn(),
      refresh: vi.fn(),
    });
  });

  it("shows loading then inventory rows", async () => {
    let resolveInventory: (value: unknown) => void = () => undefined;
    fetchAdminInventory.mockReturnValue(
      new Promise((resolve) => {
        resolveInventory = resolve;
      }),
    );

    renderList();
    expect(screen.getByRole("status")).toHaveTextContent("Loading inventory");

    resolveInventory({
      data: [
        {
          warehouse: { id: 1, code: "WH1", name: "Main" },
          product: { id: 10, name: "Scope" },
          variant: {
            id: 20,
            sku: "SCOPE-001",
            barcode: null,
            combination_label: null,
          },
          quantities: {
            on_hand: 5,
            reserved: 1,
            unreserved: 4,
            safety_stock: 0,
            available_to_sell: 4,
            reorder_point: 2,
          },
          status: { low_stock: false, out_of_stock: false },
          version: 3,
          last_movement_at: null,
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

    expect(await screen.findByText("Scope")).toBeInTheDocument();
    expect(screen.getByText("5")).toBeInTheDocument();
  });

  it("shows empty state when no rows match", async () => {
    fetchAdminInventory.mockResolvedValue({
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
      await screen.findByText("No inventory rows match these filters."),
    ).toBeInTheDocument();
  });
});
