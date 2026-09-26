import { apiGet, apiRequest } from "@/lib/api-client";
import type {
  SpatialEvaluation,
  SpatialSearchResponse,
  ViewportResponse,
  ZoneDetails,
  ZoneMatch,
} from "@/features/spatial/types/spatial-types";

type Envelope<T> = { data: T };

export type SpatialCall = {
  locale?: "ka" | "en";
  signal?: AbortSignal;
  ifNoneMatch?: string;
};

function localeHeaders(locale?: "ka" | "en"): Record<string, string> {
  return { "X-Locale": locale ?? "ka" };
}

export async function fetchViewportZones(
  params: string,
  call: SpatialCall = {},
) {
  return apiGet<Envelope<ViewportResponse>>(`/v1/spatial/zones${params}`, {
    signal: call.signal,
    headers: localeHeaders(call.locale),
    ifNoneMatch: call.ifNoneMatch,
  });
}

export async function fetchZoneDetails(
  id: string,
  geometry = false,
  call: SpatialCall = {},
) {
  const response = await apiRequest<Envelope<ZoneDetails>>(
    `/v1/spatial/zones/${id}${geometry ? "?geometry=1" : ""}`,
    { signal: call.signal, headers: localeHeaders(call.locale) },
  );
  return response.data;
}

export async function lookupCoordinate(params: string, call: SpatialCall = {}) {
  const response = await apiRequest<
    Envelope<{ zones: ZoneMatch[]; disclaimer: string }>
  >(`/v1/spatial/lookup${params}`, {
    signal: call.signal,
    headers: localeHeaders(call.locale),
  });
  return response.data;
}

export async function evaluateCoordinate(
  params: string,
  call: SpatialCall = {},
) {
  const response = await apiRequest<Envelope<SpatialEvaluation>>(
    `/v1/spatial/evaluate${params}`,
    { signal: call.signal, headers: localeHeaders(call.locale) },
  );
  return response.data;
}

export async function searchSpatialZones(
  params: string,
  call: SpatialCall = {},
) {
  const response = await apiRequest<Envelope<SpatialSearchResponse>>(
    `/v1/spatial/search${params}`,
    { signal: call.signal, headers: localeHeaders(call.locale) },
  );
  return response.data;
}
