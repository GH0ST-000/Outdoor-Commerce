import { ApiClientError } from "@/lib/api-client";
import { getApiBaseUrl, resolveServerApiBaseUrls } from "@/lib/env";
import type {
  SpeciesCard,
  SpeciesDetail,
  SpeciesFilters,
  SpeciesListResponse,
  SpeciesLocale,
  SpeciesQuery,
} from "@/features/species/types/species-types";
import { speciesQueryToSearchParams } from "@/features/species/query-state/species-search-params";

function bases(): string[] {
  return typeof window === "undefined"
    ? resolveServerApiBaseUrls()
    : [getApiBaseUrl()];
}

async function speciesFetch(
  path: string,
  locale: SpeciesLocale,
  init: RequestInit = {},
): Promise<Response> {
  const headers = {
    Accept: "application/json",
    "X-Locale": locale,
    "Accept-Language": locale,
    ...(init.headers ?? {}),
  };

  let lastError: unknown;
  for (const [index, base] of bases().entries()) {
    try {
      const response = await fetch(`${base}${path}`, { ...init, headers });
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
    : new Error("Species API unreachable");
}

async function readJson<T>(response: Response): Promise<T> {
  const payload = (await response.json()) as T & {
    error?: { code?: string; message?: string };
  };
  if (!response.ok) {
    throw new ApiClientError({
      status: response.status,
      code: payload.error?.code ?? "SPECIES_NOT_FOUND",
      message: payload.error?.message ?? "Species request failed.",
    });
  }
  return payload;
}

export async function getPublicSpeciesList(
  query: SpeciesQuery,
  locale: SpeciesLocale,
): Promise<SpeciesListResponse> {
  const qs = speciesQueryToSearchParams(query);
  const separator = qs ? "&" : "?";
  const response = await speciesFetch(
    `/v1/species${qs}${separator}locale=${locale}&per_page=10`,
    locale,
    { next: { revalidate: 60, tags: ["public-species"] } },
  );
  return readJson<SpeciesListResponse>(response);
}

export async function getPublicSpecies(
  slug: string,
  locale: SpeciesLocale,
): Promise<SpeciesDetail> {
  const response = await speciesFetch(
    `/v1/species/${encodeURIComponent(slug)}?locale=${locale}`,
    locale,
    { next: { revalidate: 60, tags: ["public-species"] } },
  );
  const payload = await readJson<{ data: SpeciesDetail }>(response);
  return payload.data;
}

export async function getPublicSpeciesFilters(
  locale: SpeciesLocale,
): Promise<SpeciesFilters> {
  const response = await speciesFetch(
    `/v1/species/filters?locale=${locale}`,
    locale,
    {
      next: { revalidate: 300, tags: ["public-species"] },
    },
  );
  const payload = await readJson<{ data: SpeciesFilters }>(response);
  return payload.data;
}

export type { SpeciesCard };
