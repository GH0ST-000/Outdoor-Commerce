import type { PaginatedResponse } from "@/features/admin/types/admin-types";

export type InventoryReasonCode =
  | "initial_count"
  | "supplier_receipt"
  | "manual_correction"
  | "stock_count_correction"
  | "customer_return"
  | "damaged"
  | "lost"
  | "expired_goods"
  | "transfer"
  | "order_sale"
  | "other";

export type WarehouseStatus = "active" | "inactive" | "archived";

export type InventoryReservationStatus =
  "active" | "committed" | "released" | "expired" | "cancelled";

export type InventoryQuantities = {
  on_hand: number;
  reserved: number;
  unreserved: number;
  safety_stock: number;
  available_to_sell: number;
  reorder_point: number;
};

export type InventoryBalanceRef = {
  warehouse: { id: number; code: string; name: string };
  product: { id: number; name: string | null };
  variant: {
    id: number;
    sku: string | null;
    barcode: string | null;
    combination_label: string | null;
  };
  quantities: InventoryQuantities;
  status: { low_stock: boolean; out_of_stock: boolean };
  version: number;
  last_movement_at: string | null;
};

export type InventoryListParams = {
  search?: string;
  warehouse_id?: number | string;
  product_id?: number | string;
  product_variant_id?: number | string;
  low_stock?: boolean | "1" | "0" | "";
  out_of_stock?: boolean | "1" | "0" | "";
  has_reservations?: boolean | "1" | "0" | "";
  locale?: string;
  sort?:
    | "on_hand"
    | "reserved"
    | "updated_at"
    | "last_movement_at"
    | "warehouse_id"
    | "product_variant_id";
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type WarehouseListItem = {
  id: number;
  code: string;
  name: string;
  status: WarehouseStatus;
  is_default: boolean;
  country_code: string | null;
  city: string | null;
  address_line_1: string | null;
  address_line_2: string | null;
  postal_code: string | null;
  latitude: number | null;
  longitude: number | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
};

export type WarehouseListParams = {
  search?: string;
  status?: WarehouseStatus | "";
  is_default?: "1" | "0" | "";
  include_deleted?: boolean | "1" | "0" | "";
  sort?: string;
  direction?: "asc" | "desc";
  per_page?: number;
  page?: number;
};

export type WarehouseWritePayload = {
  code: string;
  name: string;
  status?: WarehouseStatus;
  is_default?: boolean;
  country_code?: string;
  city?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  postal_code?: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export type InventoryLedgerEntry = {
  id: number;
  operation_id: number;
  operation_uuid: string | null;
  operation_type: string | null;
  movement_type: string;
  quantity_delta: number;
  on_hand_after: number;
  reserved_after: number;
  reason_code: string | null;
  note: string | null;
  reference: { type: string | null; id: string | null };
  actor: { id: number; name: string } | null;
  occurred_at: string | null;
  correlation_id: string | null;
  created_at: string | null;
};

export type InventoryLedgerParams = {
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: "asc" | "desc";
};

export type InventoryOperation = {
  id: number;
  uuid: string;
  type: string;
  reference: { type: string | null; id: string | null };
  reason_code: string | null;
  note: string | null;
  occurred_at: string | null;
};

export type InventoryReservation = {
  id: number;
  reservation_key: string;
  warehouse: { id: number; code: string; name: string };
  variant: { id: number; sku: string | null; barcode: string | null };
  quantity: number;
  status: InventoryReservationStatus;
  reference: { type: string | null; id: string | null };
  expires_at: string | null;
  committed_at: string | null;
  released_at: string | null;
  release_reason: string | null;
  created_at: string | null;
};

export type ReservationListParams = {
  status?: InventoryReservationStatus | "";
  warehouse_id?: number | string;
  product_variant_id?: number | string;
  reference_type?: string;
  reference_id?: string;
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: "asc" | "desc";
};

export type ReceiveInventoryPayload = {
  warehouse_id: number;
  items: { product_variant_id: number; quantity: number }[];
  reason_code: InventoryReasonCode;
  reference_type?: string | null;
  reference_id?: string | null;
  note?: string | null;
};

export type AdjustInventoryPayload = {
  warehouse_id: number;
  product_variant_id: number;
  quantity_delta: number;
  reason_code: InventoryReasonCode;
  note?: string | null;
  expected_version?: number | null;
};

export type StockCountPayload = {
  warehouse_id: number;
  product_variant_id: number;
  counted_quantity: number;
  reason_code: InventoryReasonCode;
  note?: string | null;
  expected_version?: number | null;
};

export type TransferInventoryPayload = {
  source_warehouse_id: number;
  destination_warehouse_id: number;
  items: { product_variant_id: number; quantity: number }[];
  note?: string | null;
};

export type InventorySettingsPayload = {
  safety_stock: number;
  reorder_point: number;
  expected_version?: number | null;
};

export type PaginatedInventoryBalances = PaginatedResponse<InventoryBalanceRef>;

export type PaginatedLedger = PaginatedResponse<InventoryLedgerEntry>;

export type PaginatedWarehouses = PaginatedResponse<WarehouseListItem>;

export type PaginatedReservations = PaginatedResponse<InventoryReservation>;
