import { apiRequest } from "@/lib/api-client";
import type { LegalPagination } from "@/features/legal/types/legal-types";

type Envelope<T> = { data: T; meta?: { pagination?: LegalPagination } };

export async function fetchSeasonDashboard() {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/seasons/dashboard",
  );
  return response.data;
}

export async function fetchSeasons(params = "") {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    `/v1/admin/legal/seasons${params}`,
  );
}

export async function createSeason(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/seasons",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function transitionSeason(
  id: string,
  action:
    | "submit-review"
    | "approve"
    | "publish"
    | "reject"
    | "supersede"
    | "generate-occurrences"
    | "preview",
  reason?: string,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/legal/seasons/${id}/${action}`,
    { method: "POST", body: reason ? { reason } : {} },
  );
  return response.data;
}

export async function fetchSeasonCoverage() {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/calendar/coverage",
  );
  return response.data;
}

export async function fetchSeasonOverrides() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/season-overrides",
  );
}

export async function createSeasonOverride(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/legal/season-overrides",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function fetchGenerationRuns() {
  return apiRequest<Envelope<Record<string, unknown>[]>>(
    "/v1/admin/legal/calendar-generation-runs",
  );
}
