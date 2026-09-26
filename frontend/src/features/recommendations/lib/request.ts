import type { RecommendationRequest } from "@/features/recommendations/types/recommendation-types";

const FORBIDDEN = new Set([
  "gate",
  "outcome",
  "recommendations_allowed",
  "score",
  "longitude",
  "latitude",
  "lng",
  "lat",
  "coordinate",
  "coordinates",
  "pin",
  "accuracy",
]);

export function recommendationRequestBody(
  request: RecommendationRequest,
): Record<string, unknown> {
  const body: Record<string, unknown> = {
    placement: request.placement,
    locale: request.locale,
    page: request.page ?? 1,
    per_page: 10,
  };
  if (request.contextToken) body.context_token = request.contextToken;
  if (request.activity) body.activity = request.activity;
  if (request.speciesSlug) body.species_slug = request.speciesSlug;
  if (request.speciesCategoryCode) {
    body.species_category_code = request.speciesCategoryCode;
  }
  if (request.zonePublicIds && request.zonePublicIds.length > 0) {
    body.zone_public_ids = request.zonePublicIds;
  }
  if (request.periodFrom && request.periodTo) {
    body.period_from = request.periodFrom;
    body.period_to = request.periodTo;
  }
  if (request.seasonPhase) body.season_phase = request.seasonPhase;
  if (request.regionCode && /^[A-Za-z0-9_-]{1,16}$/.test(request.regionCode)) {
    body.region_code = request.regionCode;
  }
  if (request.currency) body.currency = request.currency;

  for (const key of Object.keys(body)) {
    if (FORBIDDEN.has(key.toLowerCase())) {
      delete body[key];
    }
  }

  return body;
}

export function recommendationContextKey(
  request: RecommendationRequest,
): string {
  return [
    request.placement,
    request.locale,
    request.contextToken ?? "",
    request.activity ?? "",
    request.speciesSlug ?? "",
    request.speciesCategoryCode ?? "",
    (request.zonePublicIds ?? []).join(","),
    request.periodFrom ?? "",
    request.periodTo ?? "",
    request.seasonPhase ?? "",
    request.regionCode ?? "",
  ].join("|");
}
