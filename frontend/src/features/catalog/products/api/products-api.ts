import { apiRequest } from "@/lib/api-client";
import type {
  ApiSuccessEnvelope,
  PaginatedResponse,
} from "@/features/admin/types/admin-types";
import type {
  CatalogOption,
  ProductDetail,
  ProductListItem,
  ProductListParams,
  ProductReadiness,
  ProductStatus,
  ProductWritePayload,
} from "@/features/catalog/products/types/product-types";

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

export async function fetchAdminProducts(
  params: ProductListParams = {},
): Promise<PaginatedResponse<ProductListItem>> {
  const normalized: Record<
    string,
    string | number | boolean | undefined | null
  > = {
    search: params.search,
    status: params.status,
    brand_id: params.brand_id,
    category_id: params.category_id,
    primary_category_id: params.primary_category_id,
    is_featured:
      params.is_featured === "1"
        ? true
        : params.is_featured === "0"
          ? false
          : undefined,
    locale: params.locale,
    include_deleted:
      params.include_deleted === "1"
        ? true
        : params.include_deleted === "0"
          ? false
          : undefined,
    sort: params.sort,
    direction: params.direction,
    per_page: params.per_page,
    page: params.page,
  };

  return apiRequest<PaginatedResponse<ProductListItem>>(
    `/v1/admin/products${toQuery(normalized)}`,
  );
}

export async function fetchAdminProduct(
  id: number | string,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    `/v1/admin/products/${id}`,
  );
  return response.data;
}

export async function createAdminProduct(
  payload: ProductWritePayload,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    "/v1/admin/products",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function updateAdminProduct(
  id: number | string,
  payload: ProductWritePayload,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    `/v1/admin/products/${id}`,
    { method: "PATCH", body: payload },
  );
  return response.data;
}

export async function updateAdminProductStatus(
  id: number | string,
  status: ProductStatus,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    `/v1/admin/products/${id}/status`,
    { method: "PATCH", body: { status } },
  );
  return response.data;
}

export async function archiveAdminProduct(
  id: number | string,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    `/v1/admin/products/${id}`,
    { method: "DELETE" },
  );
  return response.data;
}

export async function restoreAdminProduct(
  id: number | string,
): Promise<ProductDetail> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductDetail>>(
    `/v1/admin/products/${id}/restore`,
    { method: "POST" },
  );
  return response.data;
}

export async function fetchAdminProductReadiness(
  id: number | string,
): Promise<ProductReadiness> {
  const response = await apiRequest<ApiSuccessEnvelope<ProductReadiness>>(
    `/v1/admin/products/${id}/readiness`,
  );
  return response.data;
}

export async function fetchCatalogCategoryOptions(): Promise<CatalogOption[]> {
  const response = await apiRequest<ApiSuccessEnvelope<CatalogOption[]>>(
    "/v1/admin/catalog/options/categories",
  );
  return Array.isArray(response.data) ? response.data : [];
}

export async function fetchCatalogBrandOptions(): Promise<CatalogOption[]> {
  const response = await apiRequest<ApiSuccessEnvelope<CatalogOption[]>>(
    "/v1/admin/catalog/options/brands",
  );
  return Array.isArray(response.data) ? response.data : [];
}
