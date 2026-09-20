import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";
import type {
  MediaAssetStatus,
  MediaAttachment,
  MediaMetadataPayload,
  MediaOwnerScope,
} from "@/features/catalog/media/types/media-types";
import { apiFormRequest, apiRequest } from "@/lib/api-client";

type MediaListResponse = {
  data: MediaAttachment[];
  meta?: { request_id?: string };
};

function productBase(productId: string | number): string {
  return `/v1/admin/products/${productId}`;
}

function mediaBase(scope: MediaOwnerScope): string {
  if (scope.kind === "product") {
    return `${productBase(scope.productId)}/media`;
  }
  return `${productBase(scope.productId)}/variants/${scope.variantId}/media`;
}

export async function fetchProductMedia(
  productId: string | number,
  locale?: string,
): Promise<MediaAttachment[]> {
  const qs = locale ? `?locale=${encodeURIComponent(locale)}` : "";
  const response = await apiRequest<MediaListResponse>(
    `${productBase(productId)}/media${qs}`,
  );
  return response.data;
}

export async function fetchVariantMedia(
  productId: string | number,
  variantId: string | number,
  locale?: string,
): Promise<MediaAttachment[]> {
  const qs = locale ? `?locale=${encodeURIComponent(locale)}` : "";
  const response = await apiRequest<MediaListResponse>(
    `${productBase(productId)}/variants/${variantId}/media${qs}`,
  );
  return response.data;
}

export async function uploadMedia(
  scope: MediaOwnerScope,
  files: File[],
): Promise<MediaAttachment[]> {
  const formData = new FormData();
  for (const file of files) {
    formData.append("files[]", file);
  }
  const response = await apiFormRequest<MediaListResponse>(mediaBase(scope), {
    formData,
  });
  return response.data ?? [];
}

export async function reorderMedia(
  scope: MediaOwnerScope,
  attachmentIds: number[],
): Promise<MediaAttachment[]> {
  const response = await apiRequest<MediaListResponse>(
    `${mediaBase(scope)}/reorder`,
    {
      method: "POST",
      body: { attachment_ids: attachmentIds },
    },
  );
  return response.data;
}

export async function updateMediaMetadata(
  scope: MediaOwnerScope,
  attachmentId: number,
  payload: MediaMetadataPayload,
): Promise<MediaAttachment> {
  const response = await apiRequest<ApiSuccessEnvelope<MediaAttachment>>(
    `${mediaBase(scope)}/${attachmentId}`,
    {
      method: "PATCH",
      body: payload,
    },
  );
  return response.data;
}

export async function setPrimaryMedia(
  scope: MediaOwnerScope,
  attachmentId: number,
): Promise<MediaAttachment> {
  const response = await apiRequest<ApiSuccessEnvelope<MediaAttachment>>(
    `${mediaBase(scope)}/${attachmentId}/primary`,
    { method: "PATCH" },
  );
  return response.data;
}

export async function removeMediaAttachment(
  scope: MediaOwnerScope,
  attachmentId: number,
): Promise<void> {
  await apiRequest(`${mediaBase(scope)}/${attachmentId}`, {
    method: "DELETE",
  });
}

export async function retryMediaProcessing(
  scope: MediaOwnerScope,
  attachmentId: number,
): Promise<MediaAssetStatus> {
  const response = await apiRequest<ApiSuccessEnvelope<MediaAssetStatus>>(
    `${mediaBase(scope)}/${attachmentId}/retry`,
    { method: "POST" },
  );
  return response.data;
}

export async function fetchMediaAssetStatus(
  assetId: number,
): Promise<MediaAssetStatus> {
  const response = await apiRequest<ApiSuccessEnvelope<MediaAssetStatus>>(
    `/v1/admin/media/${assetId}/status`,
  );
  return response.data;
}
