import type { InventoryReasonCode } from "@/features/inventory/types/inventory-types";
import { reasonCodeRequiresNote } from "@/features/inventory/utils/reason-code-labels";

export function validateReceiptForm(input: {
  quantity: string;
  reason_code: InventoryReasonCode;
  note: string;
}): Record<string, string> {
  const errors: Record<string, string> = {};
  const quantity = Number(input.quantity);
  if (!Number.isFinite(quantity) || quantity < 1) {
    errors.quantity = "Quantity must be at least 1.";
  }
  if (!input.reason_code) {
    errors.reason_code = "Reason is required.";
  }
  if (
    reasonCodeRequiresNote(input.reason_code) &&
    input.note.trim() === ""
  ) {
    errors.note = "A note is required when reason is Other.";
  }
  return errors;
}

export function validateTransferForm(input: {
  source_warehouse_id: string;
  destination_warehouse_id: string;
  quantity: string;
}): Record<string, string> {
  const errors: Record<string, string> = {};
  const quantity = Number(input.quantity);
  if (!input.source_warehouse_id) {
    errors.source_warehouse_id = "Source warehouse is required.";
  }
  if (!input.destination_warehouse_id) {
    errors.destination_warehouse_id = "Destination warehouse is required.";
  }
  if (
    input.source_warehouse_id &&
    input.destination_warehouse_id &&
    input.source_warehouse_id === input.destination_warehouse_id
  ) {
    errors.destination_warehouse_id =
      "Destination must differ from the source warehouse.";
  }
  if (!Number.isFinite(quantity) || quantity < 1) {
    errors.quantity = "Quantity must be at least 1.";
  }
  return errors;
}

export function mapApiFieldErrors(
  details: Record<string, string[]> | undefined,
): Record<string, string> {
  const next: Record<string, string> = {};
  if (!details) return next;
  for (const [key, messages] of Object.entries(details)) {
    if (messages[0]) {
      next[key] = messages[0];
    }
  }
  return next;
}
