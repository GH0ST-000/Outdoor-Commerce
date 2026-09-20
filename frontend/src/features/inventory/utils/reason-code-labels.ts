import type { InventoryReasonCode } from "@/features/inventory/types/inventory-types";

export const INVENTORY_REASON_OPTIONS: {
  value: InventoryReasonCode;
  label: string;
}[] = [
  { value: "supplier_receipt", label: "Supplier receipt" },
  { value: "initial_count", label: "Initial count" },
  { value: "manual_correction", label: "Manual correction" },
  { value: "stock_count_correction", label: "Stock count correction" },
  { value: "customer_return", label: "Customer return" },
  { value: "damaged", label: "Damaged" },
  { value: "lost", label: "Lost" },
  { value: "expired_goods", label: "Expired goods" },
  { value: "other", label: "Other" },
];

export function reasonCodeRequiresNote(code: InventoryReasonCode): boolean {
  return code === "other";
}
