import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { TransferDialog } from "@/features/inventory/components/TransferDialog";
import { TestProviders } from "@/test/providers";

const postAdminInventoryTransfer = vi.fn();
const fetchAdminWarehouses = vi.fn();

vi.mock("@/features/inventory/api/inventory-api", () => ({
  postAdminInventoryTransfer: (...args: unknown[]) =>
    postAdminInventoryTransfer(...args),
}));

vi.mock("@/features/inventory/api/warehouses-api", () => ({
  fetchAdminWarehouses: (...args: unknown[]) => fetchAdminWarehouses(...args),
}));

describe("TransferDialog", () => {
  beforeEach(() => {
    postAdminInventoryTransfer.mockReset();
    fetchAdminWarehouses.mockResolvedValue({
      data: [
        {
          id: 1,
          code: "A",
          name: "Alpha",
          status: "active",
          is_default: true,
          country_code: "GE",
          city: null,
          address_line_1: null,
          address_line_2: null,
          postal_code: null,
          latitude: null,
          longitude: null,
          created_at: null,
          updated_at: null,
          deleted_at: null,
        },
        {
          id: 2,
          code: "B",
          name: "Beta",
          status: "active",
          is_default: false,
          country_code: "GE",
          city: null,
          address_line_1: null,
          address_line_2: null,
          postal_code: null,
          latitude: null,
          longitude: null,
          created_at: null,
          updated_at: null,
          deleted_at: null,
        },
      ],
      meta: { total: 2 },
    });
  });

  it("rejects transfer when source and destination match", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <TransferDialog
          sourceWarehouseId={1}
          variantId={99}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
        />
      </TestProviders>,
    );

    await waitFor(() => {
      expect(fetchAdminWarehouses).toHaveBeenCalled();
    });

    await user.click(
      screen.getByRole("combobox", { name: "Destination warehouse" }),
    );
    await user.click(screen.getByRole("option", { name: "Alpha (A)" }));

    await user.click(screen.getByRole("button", { name: "Confirm transfer" }));

    expect(
      screen.getByText("Destination must differ from the source warehouse."),
    ).toBeInTheDocument();
    expect(postAdminInventoryTransfer).not.toHaveBeenCalled();
  });
});
