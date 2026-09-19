import { apiRequest } from "@/lib/api-client";
import type {
  ApiSuccessEnvelope,
  PaginatedResponse,
} from "@/features/admin/types/admin-types";
import type {
  AttributeDetail,
  AttributeListItem,
  AttributeListParams,
  AttributeStatus,
  AttributeValueDetail,
  AttributeValueListItem,
  AttributeValueListParams,
  AttributeValueStatus,
  AttributeValueWritePayload,
  AttributeWritePayload,
} from "@/features/catalog/attributes/types/attribute-types";

function toQuery(
  params: Record<string, string | number | boolean | undefined | null>,
): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") {
      continue;
    }
    if (typeof value === "boolean") {
      search.set(key, value ? "1" : "0");
      continue;
    }
    search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

function toTriState(value: "1" | "0" | "" | undefined): boolean | undefined {
  if (value === "1") return true;
  if (value === "0") return false;
  return undefined;
}

export async function fetchAdminAttributes(
  params: AttributeListParams = {},
): Promise<PaginatedResponse<AttributeListItem>> {
  const normalized = {
    search: params.search,
    status: params.status,
    type: params.type,
    is_filterable: toTriState(params.is_filterable),
    locale: params.locale,
    include_deleted: toTriState(params.include_deleted),
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };

  return apiRequest<PaginatedResponse<AttributeListItem>>(
    `/v1/admin/attributes${toQuery(normalized)}`,
  );
}

export async function fetchAdminAttribute(
  id: number | string,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    `/v1/admin/attributes/${id}`,
  );
  return response.data;
}

export async function createAdminAttribute(
  payload: AttributeWritePayload,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    "/v1/admin/attributes",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminAttribute(
  id: number | string,
  payload: AttributeWritePayload,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    `/v1/admin/attributes/${id}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateAdminAttributeStatus(
  id: number | string,
  status: AttributeStatus,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    `/v1/admin/attributes/${id}/status`,
    { method: "PATCH", body: { status } },
  );
  return response.data;
}

export async function archiveAdminAttribute(
  id: number | string,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    `/v1/admin/attributes/${id}`,
    { method: "DELETE" },
  );
  return response.data;
}

export async function restoreAdminAttribute(
  id: number | string,
): Promise<AttributeDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeDetail>>(
    `/v1/admin/attributes/${id}/restore`,
    { method: "POST" },
  );
  return response.data;
}

export async function fetchAdminAttributeValues(
  attributeId: number | string,
  params: AttributeValueListParams = {},
): Promise<PaginatedResponse<AttributeValueListItem>> {
  const normalized = {
    search: params.search,
    status: params.status,
    locale: params.locale,
    include_deleted: toTriState(params.include_deleted),
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };

  return apiRequest<PaginatedResponse<AttributeValueListItem>>(
    `/v1/admin/attributes/${attributeId}/values${toQuery(normalized)}`,
  );
}

export async function fetchAdminAttributeValue(
  attributeId: number | string,
  valueId: number | string,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values/${valueId}`,
  );
  return response.data;
}

export async function createAdminAttributeValue(
  attributeId: number | string,
  payload: AttributeValueWritePayload,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminAttributeValue(
  attributeId: number | string,
  valueId: number | string,
  payload: AttributeValueWritePayload,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values/${valueId}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateAdminAttributeValueStatus(
  attributeId: number | string,
  valueId: number | string,
  status: AttributeValueStatus,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values/${valueId}/status`,
    { method: "PATCH", body: { status } },
  );
  return response.data;
}

export async function archiveAdminAttributeValue(
  attributeId: number | string,
  valueId: number | string,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values/${valueId}`,
    { method: "DELETE" },
  );
  return response.data;
}

export async function restoreAdminAttributeValue(
  attributeId: number | string,
  valueId: number | string,
): Promise<AttributeValueDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<AttributeValueDetail>>(
    `/v1/admin/attributes/${attributeId}/values/${valueId}/restore`,
    { method: "POST" },
  );
  return response.data;
}
