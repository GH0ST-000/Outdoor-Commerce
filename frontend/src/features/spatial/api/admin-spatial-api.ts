import { apiFormRequest, apiRequest } from "@/lib/api-client";

type Envelope<T> = {
  data: T;
  meta?: {
    pagination?: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
};

export async function fetchSpatialDashboard() {
  const response = await apiRequest<Envelope<Record<string, number>>>(
    "/v1/admin/spatial/dashboard",
  );
  return response.data;
}

export async function fetchSpatialCoverage() {
  const response = await apiRequest<Envelope<Record<string, number>>>(
    "/v1/admin/spatial/coverage",
  );
  return response.data;
}

export async function fetchSpatialSources() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/spatial/sources",
  );
}

export async function createSpatialSource(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/spatial/sources",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function verifySpatialSource(id: string) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/sources/${id}/verify`,
    { method: "POST", body: {} },
  );
  return response.data;
}

export async function createSpatialDataset(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/spatial/datasets",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function uploadSpatialVersion(
  datasetId: string,
  formData: FormData,
) {
  const response = await apiFormRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/datasets/${datasetId}/versions`,
    { method: "POST", formData },
  );
  return response.data;
}

export async function previewSpatialVersion(id: string) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/dataset-versions/${id}/preview`,
  );
  return response.data;
}

export async function mapSpatialVersion(
  id: string,
  payload: Record<string, string>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/dataset-versions/${id}/map`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function importSpatialVersion(id: string) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/dataset-versions/${id}/import`,
    { method: "POST", body: {} },
  );
  return response.data;
}

export async function transitionSpatialVersion(
  id: string,
  action: "validate" | "submit-review" | "approve" | "publish" | "reject",
  reason?: string,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/spatial/dataset-versions/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function fetchSpatialZones() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/spatial/zones",
  );
}

export async function fetchSpatialImports() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/spatial/imports",
  );
}

export async function previewSpatialEvaluation(
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/spatial/evaluate-preview",
    { method: "POST", body: payload },
  );
  return response.data;
}
