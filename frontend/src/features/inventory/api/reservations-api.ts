import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  InventoryReservation,
  PaginatedReservations,
  ReservationListParams,
} from "@/features/inventory/types/inventory-types";
import {
  newIdempotencyKey,
  toInventoryQuery,
} from "@/features/inventory/utils/inventory-query";

export async function fetchAdminInventoryReservations(
  params: ReservationListParams = {},
): Promise<PaginatedReservations> {
  return apiRequest<PaginatedReservations>(
    `/v1/admin/inventory/reservations${toInventoryQuery(params)}`,
  );
}

export async function fetchAdminInventoryReservation(
  id: number | string,
): Promise<InventoryReservation> {
  const response = await apiRequest<ApiSuccessEnvelope<InventoryReservation>>(
    `/v1/admin/inventory/reservations/${id}`,
  );
  return response.data;
}

export async function releaseAdminInventoryReservation(
  id: number | string,
  releaseReason?: string | null,
): Promise<InventoryReservation> {
  const response = await apiRequest<ApiSuccessEnvelope<InventoryReservation>>(
    `/v1/admin/inventory/reservations/${id}/release`,
    {
      method: "POST",
      body: { release_reason: releaseReason ?? null },
      headers: { "Idempotency-Key": newIdempotencyKey() },
    },
  );
  return response.data;
}

export async function cancelAdminInventoryReservation(
  id: number | string,
  releaseReason?: string | null,
): Promise<InventoryReservation> {
  const response = await apiRequest<ApiSuccessEnvelope<InventoryReservation>>(
    `/v1/admin/inventory/reservations/${id}/cancel`,
    {
      method: "POST",
      body: { release_reason: releaseReason ?? null },
      headers: { "Idempotency-Key": newIdempotencyKey() },
    },
  );
  return response.data;
}
