import { apiRequest } from "@/lib/api-client";
import type {
  ApiSuccessEnvelope,
  PaginatedResponse,
} from "@/features/admin/types/admin-types";
import type {
  ProductVariantAxes,
  ProductVariantDetail,
  ProductVariantListItem,
  ProductVariantListParams,
  ProductVariantStatus,
  ProductVariantWritePayload,
  VariantAxisSelection,
  VariantGenerationPreview,
  VariantGenerationResult,
  VariantGenerationSummary,
} from "@/features/catalog/variants/types/variant-types";

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

export async function fetchProductVariantAxes(
  productId: number | string,
): Promise<ProductVariantAxes> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantAxes>>(
    `/v1/admin/products/${productId}/variant-axes`,
  );
  return response.data;
}

export async function saveProductVariantAxes(
  productId: number | string,
  axes: { attribute_id: number; sort_order: number }[],
): Promise<ProductVariantAxes> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantAxes>>(
    `/v1/admin/products/${productId}/variant-axes`,
    { method: "PUT", body: { axes } },
  );
  return response.data;
}

export async function fetchProductVariants(
  productId: number | string,
  params: ProductVariantListParams = {},
): Promise<PaginatedResponse<ProductVariantListItem>> {
  const normalized = {
    search: params.search,
    status: params.status,
    is_default: toTriState(params.is_default),
    include_deleted: toTriState(params.include_deleted),
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };

  return apiRequest<PaginatedResponse<ProductVariantListItem>>(
    `/v1/admin/products/${productId}/variants${toQuery(normalized)}`,
  );
}

export async function fetchProductVariant(
  productId: number | string,
  variantId: number | string,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}`,
  );
  return response.data;
}

export async function createProductVariant(
  productId: number | string,
  payload: ProductVariantWritePayload,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateProductVariant(
  productId: number | string,
  variantId: number | string,
  payload: ProductVariantWritePayload,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateProductVariantStatus(
  productId: number | string,
  variantId: number | string,
  status: ProductVariantStatus,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}/status`,
    { method: "PATCH", body: { status } },
  );
  return response.data;
}

export async function setDefaultProductVariant(
  productId: number | string,
  variantId: number | string,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}/default`,
    { method: "POST" },
  );
  return response.data;
}

export async function archiveProductVariant(
  productId: number | string,
  variantId: number | string,
  replacementVariantId?: number | null,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}`,
    {
      method: "DELETE",
      body:
        replacementVariantId != null
          ? { replacement_variant_id: replacementVariantId }
          : undefined,
    },
  );
  return response.data;
}

export async function restoreProductVariant(
  productId: number | string,
  variantId: number | string,
): Promise<ProductVariantDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductVariantDetail>>(
    `/v1/admin/products/${productId}/variants/${variantId}/restore`,
    { method: "POST" },
  );
  return response.data;
}

export async function previewProductVariantGeneration(
  productId: number | string,
  axes: VariantAxisSelection[],
): Promise<VariantGenerationPreview> {
  const response = await apiRequest<
    ApiSuccessEnvelope<VariantGenerationPreview>
  >(`/v1/admin/products/${productId}/variants/generate-preview`, {
    method: "POST",
    body: { axes },
  });
  return response.data;
}

export async function generateProductVariants(
  productId: number | string,
  axes: VariantAxisSelection[],
  status: ProductVariantStatus = "draft",
): Promise<VariantGenerationResult> {
  const response = await apiRequest<{
    data: ProductVariantListItem[];
    meta?: { summary?: VariantGenerationSummary };
  }>(`/v1/admin/products/${productId}/variants/generate`, {
    method: "POST",
    body: { axes, status },
  });

  return {
    created: Array.isArray(response.data) ? response.data : [],
    summary: response.meta?.summary ?? null,
  };
}
