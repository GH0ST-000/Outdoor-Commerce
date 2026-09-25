import { ApiClientError } from "@/lib/api-client";
import { getApiBaseUrl, resolveServerApiBaseUrls } from "@/lib/env";

export type AvailabilityState =
  "open" | "partially_open" | "closed" | "conditional" | "unknown" | "conflict";

export type AvailabilityMode = "any_date" | "entire_period" | "timeline";

export type AvailabilityWindow = {
  from: string;
  to: string;
  state: string;
};

export type AvailabilityResult = {
  species: {
    id: string;
    slug: string;
    common_name: string | null;
    scientific_name: string;
    media: { url: string | null } | null;
  };
  overall_state: AvailabilityState;
  mode: AvailabilityMode;
  available_windows: AvailabilityWindow[];
  closed_windows: AvailabilityWindow[];
  conditional_windows: AvailabilityWindow[];
  unknown_windows: AvailabilityWindow[];
  conflicting_windows: AvailabilityWindow[];
  timeline: AvailabilityWindow[];
  next_opening: { at: string; local_date: string } | null;
  next_closing: {
    at: string;
    local_end_date_inclusive: string;
  } | null;
  limits: {
    limit_type: string;
    amount: string | number | null;
    unit: string | null;
    period: string;
  }[];
  conditions: { condition_type: string; string_value: string | null }[];
  citations: {
    official_url: string | null;
    source_name: string | null;
    reference_code: string | null;
  }[];
  last_verified_at: string | null;
  region_code: string | null;
  disclaimer: string;
};

export type AvailabilityResponse = {
  query: {
    activity: string;
    from: string;
    to: string;
    timezone: string;
    mode: string;
    region_code: string | null;
  };
  results: AvailabilityResult[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
  disclaimer: string;
};

function bases(): string[] {
  return typeof window === "undefined"
    ? resolveServerApiBaseUrls()
    : [getApiBaseUrl()];
}

async function outdoorFetch(
  path: string,
  locale: "ka" | "en",
): Promise<Response> {
  const headers = {
    Accept: "application/json",
    "X-Locale": locale,
    "Accept-Language": locale,
  };
  let lastError: unknown;
  for (const [index, base] of bases().entries()) {
    try {
      const response = await fetch(`${base}${path}`, { headers });
      if (
        !response.ok &&
        response.status >= 500 &&
        index < bases().length - 1
      ) {
        continue;
      }
      return response;
    } catch (error) {
      lastError = error;
    }
  }
  throw lastError instanceof Error
    ? lastError
    : new Error("Season API unreachable");
}

async function parse<T>(response: Response): Promise<T> {
  const body = (await response.json().catch(() => ({}))) as {
    data?: T;
    error?: { code?: string; message?: string };
  };
  if (!response.ok) {
    throw new ApiClientError({
      status: response.status,
      code: body.error?.code ?? "SEASON_ERROR",
      message: body.error?.message ?? "Season request failed",
    });
  }
  return body.data as T;
}

export async function fetchAvailability(
  params: {
    activity: "hunting" | "fishing";
    from: string;
    to: string;
    mode: AvailabilityMode;
    region?: string;
    species?: string;
    page?: number;
  },
  locale: "ka" | "en",
): Promise<AvailabilityResponse> {
  const search = new URLSearchParams({
    activity: params.activity,
    from: params.from,
    to: params.to,
    mode: params.mode,
    page: String(params.page ?? 1),
    per_page: "10",
  });
  if (params.region) search.set("region", params.region);
  if (params.species) search.set("species", params.species);
  const response = await outdoorFetch(
    `/v1/outdoor/availability?${search.toString()}`,
    locale,
  );
  return parse<AvailabilityResponse>(response);
}

export async function fetchSpeciesSeasons(
  slug: string,
  params: { from: string; to: string; region?: string },
  locale: "ka" | "en",
): Promise<{ availability: AvailabilityResult | null; disclaimer: string }> {
  const search = new URLSearchParams({
    from: params.from,
    to: params.to,
  });
  if (params.region) search.set("region", params.region);
  const response = await outdoorFetch(
    `/v1/species/${encodeURIComponent(slug)}/seasons?${search.toString()}`,
    locale,
  );
  return parse(response);
}
