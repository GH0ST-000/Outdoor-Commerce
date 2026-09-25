import { apiFormRequest, apiRequest } from "@/lib/api-client";
import type {
  LegalDashboard,
  LegalPagination,
} from "@/features/legal/types/legal-types";

type Envelope<T> = { data: T; meta?: { pagination?: LegalPagination } };

export async function fetchLegalDashboard() {
  const response = await apiRequest<Envelope<LegalDashboard>>(
    "/v1/admin/legal/dashboard",
  );
  return response.data;
}

export async function fetchLegalSources(params = "") {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    `/v1/admin/legal/sources${params}`,
  );
}

export async function createLegalAuthority(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/authorities",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function createLegalSource(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/sources",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function transitionLegalSource(
  id: string,
  action: "submit-review" | "verify" | "reject" | "check-for-changes",
  reason?: string,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/sources/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function fetchLegalDocuments() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/documents",
  );
}

export async function createLegalDocument(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/documents",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function uploadLegalVersion(
  documentId: string,
  formData: FormData,
) {
  const response = await apiFormRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/documents/${documentId}/versions`,
    { method: "POST", formData },
  );
  return response.data;
}

export async function transitionLegalVersion(
  id: string,
  action: "submit-review" | "approve" | "reject",
  reason?: string,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/versions/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function createLegalProvision(
  versionId: string,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/versions/${versionId}/provisions`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function fetchLegalRules(params = "") {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    `/v1/admin/legal/rules${params}`,
  );
}

export async function createLegalRule(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/rules",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function transitionLegalRule(
  id: string,
  action: "submit-review" | "approve" | "publish" | "reject" | "supersede",
  reason?: string,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/rules/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function fetchLegalConflicts() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/conflicts",
  );
}

export async function resolveLegalConflict(id: string, reason: string) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/conflicts/${id}/resolve`,
    { method: "POST", body: { reason } },
  );
  return response.data;
}

export async function previewLegalEvaluation(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/evaluate",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function fetchLegalAuthorities() {
  const response = await apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/authorities",
  );
  return response.data;
}

export async function fetchChangeDetections() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/change-detections",
  );
}

export async function dismissChangeDetection(id: string, reason: string) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/change-detections/${id}/dismiss`,
    { method: "POST", body: { reason } },
  );
  return response.data;
}
