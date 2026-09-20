import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  AdjustInventoryPayload,
  InventoryBalanceRef,
  InventoryLedgerParams,
  InventoryListParams,
  InventoryOperation,
  InventorySettingsPayload,
  PaginatedInventoryBalances,
  PaginatedLedger,
  ReceiveInventoryPayload,
  StockCountPayload,
  TransferInventoryPayload,
} from "@/features/inventory/types/inventory-types";
import {
  newIdempotencyKey,
  toInventoryQuery,
} from "@/features/inventory/utils/inventory-query";

function normalizeListParams(
  params: InventoryListParams,
): Record<string, string | number | boolean | undefined | null> {
  return {
    search: params.search,
    warehouse_id: params.warehouse_id,
    product_id: params.product_id,
    product_variant_id: params.product_variant_id,
    low_stock:
      params.low_stock === "1" || params.low_stock === true
        ? true
        : params.low_stock === "0" || params.low_stock === false
          ? false
          : undefined,
    out_of_stock:
      params.out_of_stock === "1" || params.out_of_stock === true
        ? true
        : params.out_of_stock === "0" || params.out_of_stock === false
          ? false
          : undefined,
    has_reservations:
      params.has_reservations === "1" || params.has_reservations === true
        ? true
        : params.has_reservations === "0" || params.has_reservations === false
          ? false
          : undefined,
    locale: params.locale,
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };
}

export async function fetchAdminInventory(
  params: InventoryListParams = {},
): Promise<PaginatedInventoryBalances> {
  return apiRequest<PaginatedInventoryBalances>(
    `/v1/admin/inventory${toInventoryQuery(normalizeListParams(params))}`,
  );
}

export async function fetchAdminInventoryBalance(
  warehouseId: number | string,
  variantId: number | string,
): Promise<InventoryBalanceRef> {
  const response = await apiRequest<ApiSuccessEnvelope<InventoryBalanceRef>>(
    `/v1/admin/inventory/${warehouseId}/${variantId}`,
  );
  return response.data;
}

export async function fetchAdminInventoryLedger(
  warehouseId: number | string,
  variantId: number | string,
  params: InventoryLedgerParams = {},
): Promise<PaginatedLedger> {
  return apiRequest<PaginatedLedger>(
    `/v1/admin/inventory/${warehouseId}/${variantId}/ledger${toInventoryQuery(params)}`,
  );
}

export async function updateAdminInventorySettings(
  warehouseId: number | string,
  variantId: number | string,
  payload: InventorySettingsPayload,
): Promise<InventoryBalanceRef> {
  const response = await apiRequest<ApiSuccessEnvelope<InventoryBalanceRef>>(
    `/v1/admin/inventory/${warehouseId}/${variantId}/settings`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

type MutationEnvelope<T> = ApiSuccessEnvelope<T> & {
  meta?: { request_id?: string; replay?: boolean };
};

export async function postAdminInventoryReceipt(
  payload: ReceiveInventoryPayload,
): Promise<{ operation: InventoryOperation; balances: InventoryBalanceRef[] }> {
  const response = await apiRequest<
    MutationEnvelope<{
      operation: InventoryOperation;
      balances: InventoryBalanceRef[];
    }>
  >("/v1/admin/inventory/receipts", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": newIdempotencyKey() },
  });
  return response.data;
}

export async function postAdminInventoryAdjustment(
  payload: AdjustInventoryPayload,
): Promise<{
  operation: InventoryOperation;
  balance: InventoryBalanceRef | null;
}> {
  const response = await apiRequest<
    MutationEnvelope<{
      operation: InventoryOperation;
      balance: InventoryBalanceRef | null;
    }>
  >("/v1/admin/inventory/adjustments", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": newIdempotencyKey() },
  });
  return response.data;
}

export async function postAdminInventoryStockCount(
  payload: StockCountPayload,
): Promise<{
  operation: InventoryOperation;
  balance: InventoryBalanceRef | null;
}> {
  const response = await apiRequest<
    MutationEnvelope<{
      operation: InventoryOperation;
      balance: InventoryBalanceRef | null;
    }>
  >("/v1/admin/inventory/stock-counts", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": newIdempotencyKey() },
  });
  return response.data;
}

export async function postAdminInventoryTransfer(
  payload: TransferInventoryPayload,
): Promise<{ operation: InventoryOperation; balances: InventoryBalanceRef[] }> {
  const response = await apiRequest<
    MutationEnvelope<{
      operation: InventoryOperation;
      balances: InventoryBalanceRef[];
    }>
  >("/v1/admin/inventory/transfers", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": newIdempotencyKey() },
  });
  return response.data;
}
