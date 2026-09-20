import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  BulkPricePayload,
  CreatePricePeriodPayload,
  PaginatedVariantPrices,
  PricePeriod,
  ReplacePricePayload,
  UpdatePricePeriodPayload,
  VariantPriceDetail,
  VariantPriceListParams,
  VariantPriceRow,
} from "@/features/pricing/types/pricing-types";
import { toPricingQuery } from "@/features/pricing/utils/pricing-query";

function normalizeVariantPriceParams(
  params: VariantPriceListParams,
): Record<string, string | number | boolean | undefined | null> {
  return {
    search: params.search,
    price_list_id: params.price_list_id,
    product_id: params.product_id,
    product_variant_id: params.product_variant_id,
    pricing_ready:
      params.pricing_ready === "1" || params.pricing_ready === true
        ? true
        : params.pricing_ready === "0" || params.pricing_ready === false
          ? false
          : undefined,
    period_status: params.period_status,
    locale: params.locale,
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };
}

export async function fetchAdminVariantPrices(
  params: VariantPriceListParams = {},
): Promise<PaginatedVariantPrices> {
  return apiRequest<PaginatedVariantPrices>(
    `/v1/admin/prices${toPricingQuery(normalizeVariantPriceParams(params))}`,
  );
}

export async function fetchAdminVariantPrice(
  priceListId: number | string,
  variantId: number | string,
): Promise<VariantPriceDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<VariantPriceDetail>>(
    `/v1/admin/price-lists/${priceListId}/variants/${variantId}/prices`,
  );
  return response.data;
}

export async function createAdminVariantPrice(
  priceListId: number | string,
  variantId: number | string,
  payload: CreatePricePeriodPayload,
): Promise<VariantPriceRow> {
  const response = await apiRequest<ApiSuccessEnvelope<VariantPriceRow>>(
    `/v1/admin/price-lists/${priceListId}/variants/${variantId}/prices`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function replaceAdminVariantPrice(
  priceListId: number | string,
  variantId: number | string,
  payload: ReplacePricePayload,
): Promise<VariantPriceRow> {
  const response = await apiRequest<ApiSuccessEnvelope<VariantPriceRow>>(
    `/v1/admin/price-lists/${priceListId}/variants/${variantId}/prices/replace`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function fetchAdminPricePeriod(
  periodId: number | string,
): Promise<PricePeriod> {
  const response = await apiRequest<ApiSuccessEnvelope<PricePeriod>>(
    `/v1/admin/price-periods/${periodId}`,
  );
  return response.data;
}

export async function updateAdminPricePeriod(
  periodId: number | string,
  payload: UpdatePricePeriodPayload,
): Promise<PricePeriod> {
  const response = await apiRequest<ApiSuccessEnvelope<PricePeriod>>(
    `/v1/admin/price-periods/${periodId}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function publishAdminPricePeriod(
  periodId: number | string,
): Promise<PricePeriod> {
  const response = await apiRequest<ApiSuccessEnvelope<PricePeriod>>(
    `/v1/admin/price-periods/${periodId}/publish`,
    { method: "POST" },
  );
  return response.data;
}

export async function cancelAdminPricePeriod(
  periodId: number | string,
): Promise<PricePeriod> {
  const response = await apiRequest<ApiSuccessEnvelope<PricePeriod>>(
    `/v1/admin/price-periods/${periodId}/cancel`,
    { method: "POST" },
  );
  return response.data;
}

export async function bulkAdminPrices(
  payload: BulkPricePayload,
): Promise<{ updated: number; created: number }> {
  const response = await apiRequest<
    ApiSuccessEnvelope<{ updated: number; created: number }>
  >("/v1/admin/prices/bulk", { method: "POST", body: payload });
  return response.data;
}
