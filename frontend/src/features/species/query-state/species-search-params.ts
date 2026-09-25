import type { SpeciesQuery } from "@/features/species/types/species-types";

export type SearchParamsInput = Record<string, string | string[] | undefined>;

function first(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export function parseSpeciesSearchParams(
  input: SearchParamsInput,
): SpeciesQuery {
  const pageRaw = first(input.page);
  const page =
    pageRaw && /^\d+$/.test(pageRaw) ? Math.max(1, Number(pageRaw)) : 1;
  const sort = first(input.sort);
  const allowed = new Set(["name", "newest", "scientific"]);

  return {
    q: first(input.q)?.trim() || undefined,
    activity_type: first(input.activity_type) || undefined,
    domain_type: first(input.domain_type) || undefined,
    taxonomy: first(input.taxonomy) || undefined,
    habitat: first(input.habitat) || undefined,
    conservation_status: first(input.conservation_status) || undefined,
    sort: sort && allowed.has(sort) ? sort : "name",
    page,
  };
}

export function speciesQueryToSearchParams(query: SpeciesQuery): string {
  const params = new URLSearchParams();
  if (query.q) params.set("q", query.q);
  if (query.activity_type) params.set("activity_type", query.activity_type);
  if (query.domain_type) params.set("domain_type", query.domain_type);
  if (query.taxonomy) params.set("taxonomy", query.taxonomy);
  if (query.habitat) params.set("habitat", query.habitat);
  if (query.conservation_status) {
    params.set("conservation_status", query.conservation_status);
  }
  if (query.sort !== "name") params.set("sort", query.sort);
  if (query.page > 1) params.set("page", String(query.page));
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

export function emptySpeciesQuery(): SpeciesQuery {
  return { sort: "name", page: 1 };
}
