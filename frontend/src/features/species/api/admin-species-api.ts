import { apiFormRequest, apiRequest } from "@/lib/api-client";
import type { ApiSuccessEnvelope } from "@/features/admin/types/admin-types";

export type AdminSpeciesListItem = {
  id: string;
  slug: string;
  scientific_name: string;
  common_name: string | null;
  publication_status: string;
  verification_status: string;
  activity_type: string;
  domain_type: string;
  content_version: number;
  updated_at: string | null;
};

type AdminEnvelope = ApiSuccessEnvelope<Record<string, unknown>>;

export async function fetchAdminSpecies(page = 1) {
  return apiRequest<{
    data: AdminSpeciesListItem[];
    meta: {
      pagination: { current_page: number; last_page: number; total: number };
    };
  }>(`/v1/admin/species?page=${page}`);
}

export async function fetchAdminSpeciesDetail(id: string) {
  const response = await apiRequest<AdminEnvelope>(`/v1/admin/species/${id}`);
  return response.data;
}

export async function createAdminSpecies(payload: Record<string, unknown>) {
  const response = await apiRequest<AdminEnvelope>("/v1/admin/species", {
    method: "POST",
    body: payload,
  });
  return response.data;
}

export async function updateAdminSpecies(
  id: string,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<AdminEnvelope>(`/v1/admin/species/${id}`, {
    method: "PATCH",
    body: payload,
  });
  return response.data;
}

export async function transitionAdminSpecies(
  id: string,
  action: "submit-review" | "publish" | "unpublish" | "archive",
  reason?: string,
) {
  const response = await apiRequest<AdminEnvelope>(
    `/v1/admin/species/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function createAdminSpeciesAlias(
  id: string,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<AdminEnvelope>(
    `/v1/admin/species/${id}/aliases`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function createAdminSpeciesSource(
  id: string,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<AdminEnvelope>(
    `/v1/admin/species/${id}/sources`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function createAdminSimilarSpecies(
  id: string,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<AdminEnvelope>(
    `/v1/admin/species/${id}/similar-species`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function uploadAdminSpeciesMedia(id: string, formData: FormData) {
  return apiFormRequest<AdminEnvelope>(`/v1/admin/species/${id}/media`, {
    formData,
  });
}

export async function fetchAdminSpeciesRevisions(id: string) {
  return apiRequest<{
    data: Array<{
      revision_number: number;
      change_summary: string;
      actor_id: number | null;
      created_at: string | null;
      snapshot: Record<string, unknown>;
    }>;
  }>(`/v1/admin/species/${id}/revisions`);
}

export async function restoreAdminSpeciesRevision(
  id: string,
  revision: number,
) {
  const response = await apiRequest<AdminEnvelope>(
    `/v1/admin/species/${id}/revisions/${revision}/restore`,
    { method: "POST" },
  );
  return response.data;
}
