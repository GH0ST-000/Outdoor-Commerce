import { apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  PaginatedPromotions,
  Promotion,
  PromotionListParams,
  PromotionPreviewPayload,
  PromotionPreviewResult,
  PromotionTargetsPayload,
  PromotionWritePayload,
} from "@/features/pricing/types/pricing-types";
import { toPricingQuery } from "@/features/pricing/utils/pricing-query";

function normalizePromotionParams(
  params: PromotionListParams,
): Record<string, string | number | boolean | undefined | null> {
  return {
    search: params.search,
    status: params.status,
    discount_type: params.discount_type,
    lifecycle: params.lifecycle,
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

export async function fetchAdminPromotions(
  params: PromotionListParams = {},
): Promise<PaginatedPromotions> {
  return apiRequest<PaginatedPromotions>(
    `/v1/admin/promotions${toPricingQuery(normalizePromotionParams(params))}`,
  );
}

export async function fetchAdminPromotion(
  id: number | string,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}`,
  );
  return response.data;
}

export async function createAdminPromotion(
  payload: PromotionWritePayload,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    "/v1/admin/promotions",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminPromotion(
  id: number | string,
  payload: PromotionWritePayload,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function activateAdminPromotion(
  id: number | string,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}/activate`,
    { method: "POST" },
  );
  return response.data;
}

export async function pauseAdminPromotion(
  id: number | string,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}/pause`,
    { method: "POST" },
  );
  return response.data;
}

export async function archiveAdminPromotion(
  id: number | string,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}/archive`,
    { method: "POST" },
  );
  return response.data;
}

export async function restoreAdminPromotion(
  id: number | string,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}/restore`,
    { method: "POST" },
  );
  return response.data;
}

export async function updateAdminPromotionTargets(
  id: number | string,
  payload: PromotionTargetsPayload,
): Promise<Promotion> {
  const response = await apiRequest<ApiSuccessEnvelope<Promotion>>(
    `/v1/admin/promotions/${id}/targets`,
    { method: "PUT", body: payload },
  );
  return response.data;
}

export async function previewAdminPromotion(
  payload: PromotionPreviewPayload,
): Promise<PromotionPreviewResult> {
  const response = await apiRequest<ApiSuccessEnvelope<PromotionPreviewResult>>(
    "/v1/admin/promotions/preview",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function previewAdminPromotionById(
  id: number | string,
  payload: PromotionPreviewPayload = {},
): Promise<PromotionPreviewResult> {
  const response = await apiRequest<ApiSuccessEnvelope<PromotionPreviewResult>>(
    `/v1/admin/promotions/${id}/preview`,
    { method: "POST", body: payload },
  );
  return response.data;
}
