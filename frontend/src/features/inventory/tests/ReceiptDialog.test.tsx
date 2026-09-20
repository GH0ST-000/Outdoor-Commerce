import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ReceiptDialog } from "@/features/inventory/components/ReceiptDialog";
import { TestProviders } from "@/test/providers";

const postAdminInventoryReceipt = vi.fn();

vi.mock("@/features/inventory/api/inventory-api", () => ({
  postAdminInventoryReceipt: (...args: unknown[]) =>
    postAdminInventoryReceipt(...args),
}));

describe("ReceiptDialog", () => {
  beforeEach(() => {
    postAdminInventoryReceipt.mockReset();
  });

  it("requires a note when reason is Other", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <ReceiptDialog
          warehouseId={1}
          variantId={2}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
        />
      </TestProviders>,
    );

    const quantityInput = screen.getByLabelText("Quantity");
    fireEvent.change(quantityInput, { target: { value: "0" } });
    fireEvent.submit(
      screen.getByRole("button", { name: "Confirm receipt" }).closest("form")!,
    );

    expect(screen.getByText("Quantity must be at least 1.")).toBeInTheDocument();
    expect(postAdminInventoryReceipt).not.toHaveBeenCalled();

    fireEvent.change(quantityInput, { target: { value: "2" } });
    await user.click(screen.getByRole("combobox", { name: "Reason" }));
    await user.click(screen.getByRole("option", { name: "Other" }));
    fireEvent.submit(
      screen.getByRole("button", { name: "Confirm receipt" }).closest("form")!,
    );

    expect(
      screen.getByText("A note is required when reason is Other."),
    ).toBeInTheDocument();
    expect(postAdminInventoryReceipt).not.toHaveBeenCalled();
  });
});
