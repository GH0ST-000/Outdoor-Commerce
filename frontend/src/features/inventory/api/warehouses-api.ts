import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  PaginatedWarehouses,
  WarehouseListItem,
  WarehouseListParams,
  WarehouseStatus,
  WarehouseWritePayload,
} from "@/features/inventory/types/inventory-types";
import { toInventoryQuery } from "@/features/inventory/utils/inventory-query";

function normalizeWarehouseParams(
  params: WarehouseListParams,
): Record<string, string | number | boolean | undefined | null> {
  return {
    search: params.search,
    status: params.status,
    is_default: params.is_default,
    include_deleted:
      params.include_deleted === "1" || params.include_deleted === true
        ? true
        : params.include_deleted === "0" || params.include_deleted === false
          ? false
          : undefined,
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };
}

export async function fetchAdminWarehouses(
  params: WarehouseListParams = {},
): Promise<PaginatedWarehouses> {
  return apiRequest<PaginatedWarehouses>(
    `/v1/admin/warehouses${toInventoryQuery(normalizeWarehouseParams(params))}`,
  );
}

export async function fetchAdminWarehouse(
  id: number | string,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}`,
  );
  return response.data;
}

export async function createAdminWarehouse(
  payload: WarehouseWritePayload,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    "/v1/admin/warehouses",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminWarehouse(
  id: number | string,
  payload: WarehouseWritePayload,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateAdminWarehouseStatus(
  id: number | string,
  status: WarehouseStatus,
  replacementDefaultWarehouseId?: number | null,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}/status`,
    {
      method: "PATCH",
      body: {
        status,
        replacement_default_warehouse_id: replacementDefaultWarehouseId ?? null,
      },
    },
  );
  return response.data;
}

export async function setAdminWarehouseDefault(
  id: number | string,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}/default`,
    { method: "PATCH" },
  );
  return response.data;
}

export async function archiveAdminWarehouse(
  id: number | string,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}`,
    { method: "DELETE" },
  );
  return response.data;
}

export async function restoreAdminWarehouse(
  id: number | string,
): Promise<WarehouseListItem> {
  const response = await apiRequest<ApiSuccessEnvelope<WarehouseListItem>>(
    `/v1/admin/warehouses/${id}/restore`,
    { method: "POST" },
  );
  return response.data;
}
