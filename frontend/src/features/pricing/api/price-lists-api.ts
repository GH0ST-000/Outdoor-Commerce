import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  PaginatedPriceLists,
  PriceList,
  PriceListListParams,
  PriceListStatus,
  PriceListWritePayload,
} from "@/features/pricing/types/pricing-types";
import { toPricingQuery } from "@/features/pricing/utils/pricing-query";

function normalizePriceListParams(
  params: PriceListListParams,
): Record<string, string | number | boolean | undefined | null> {
  return {
    search: params.search,
    status: params.status,
    currency_code: params.currency_code,
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

export async function fetchAdminPriceLists(
  params: PriceListListParams = {},
): Promise<PaginatedPriceLists> {
  return apiRequest<PaginatedPriceLists>(
    `/v1/admin/price-lists${toPricingQuery(normalizePriceListParams(params))}`,
  );
}

export async function fetchAdminPriceList(
  id: number | string,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}`,
  );
  return response.data;
}

export async function createAdminPriceList(
  payload: PriceListWritePayload,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    "/v1/admin/price-lists",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminPriceList(
  id: number | string,
  payload: PriceListWritePayload,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateAdminPriceListStatus(
  id: number | string,
  status: PriceListStatus,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}/status`,
    { method: "PATCH", body: { status } },
  );
  return response.data;
}

export async function setAdminPriceListDefault(
  id: number | string,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}/default`,
    { method: "PATCH" },
  );
  return response.data;
}

export async function archiveAdminPriceList(
  id: number | string,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}`,
    { method: "DELETE" },
  );
  return response.data;
}

export async function restoreAdminPriceList(
  id: number | string,
): Promise<PriceList> {
  const response = await apiRequest<ApiSuccessEnvelope<PriceList>>(
    `/v1/admin/price-lists/${id}/restore`,
    { method: "POST" },
  );
  return response.data;
}
