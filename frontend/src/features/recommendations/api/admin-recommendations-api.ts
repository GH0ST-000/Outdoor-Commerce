import { apiRequest } from "@/lib/api-client";

type Envelope<T> = { data: T };

export type CoverageReport = {
  products_without_activity: number;
  products_without_assignments: number;
  contradictory_assignments: number;
  low_confidence_sources: number;
  equipment_terms_without_products: string[];
  active_profiles: Array<{ placement: string; version: number; slug: string }>;
  scheduled_merchandising: number;
  expiring_assignments: number;
};

export type RecommendationProfileRow = {
  public_id: string;
  name: string;
  placement: string;
  version: number;
  status: string;
};

const DEFAULT_WEIGHTS: Record<string, number> = {
  activity_match: 18,
  species_exact_match: 22,
  species_category_match: 10,
  required_equipment_match: 18,
  method_match: 8,
  season_phase_match: 6,
  region_match: 5,
  zone_type_match: 5,
  general_relevance: 0,
  specification_completeness: 4,
  availability: 4,
  popularity: 0,
  recency: 0,
};

const TIE_BREAK = [
  "pinned",
  "pin_priority",
  "final_score",
  "confidence",
  "in_stock",
  "merchandising_priority",
  "published_at",
  "slug",
];

export function defaultProfilePayload(name: string, placement: string) {
  return {
    name,
    placement,
    weights: DEFAULT_WEIGHTS,
    configuration: {
      minimum_score: 35,
      maximum_results: 8,
      candidate_limit: 80,
      merchandising_max_points: 8,
      activity_only_cap: 60,
      stock_behavior: "hide_out_of_stock",
      backorder_behavior: "unsupported",
      tie_break: TIE_BREAK,
      weights: DEFAULT_WEIGHTS,
    },
  };
}

export async function fetchRecommendationCoverage() {
  const response = await apiRequest<Envelope<CoverageReport>>(
    "/v1/admin/recommendations/coverage",
  );
  return response.data;
}

export async function fetchRecommendationProfiles() {
  const response = await apiRequest<
    Envelope<{ data: RecommendationProfileRow[] }>
  >("/v1/admin/recommendations/profiles");
  return response.data.data ?? [];
}

export async function createRecommendationProfile(
  name: string,
  placement: string,
) {
  const response = await apiRequest<Envelope<RecommendationProfileRow>>(
    "/v1/admin/recommendations/profiles",
    { method: "POST", body: defaultProfilePayload(name, placement) },
  );
  return response.data;
}

export async function transitionRecommendationProfile(
  publicId: string,
  action: "submit-review" | "approve" | "publish" | "supersede",
) {
  const response = await apiRequest<Envelope<RecommendationProfileRow>>(
    `/v1/admin/recommendations/profiles/${publicId}/${action}`,
    { method: "POST", body: {} },
  );
  return response.data;
}

export async function createMerchandisingRule(
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/recommendations/merchandising-rules",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function simulateRecommendations(
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/recommendations/simulate",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function fetchTaxonomyTerms() {
  const response = await apiRequest<
    Envelope<{
      data: Array<{
        public_id: string;
        dimension: string;
        code: string;
        default_label: string;
      }>;
    }>
  >("/v1/admin/recommendations/terms");
  return response.data.data ?? [];
}

export async function createTaxonomyTerm(payload: Record<string, unknown>) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    "/v1/admin/recommendations/terms",
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function createProductAssignment(
  productId: number,
  payload: Record<string, unknown>,
) {
  const response = await apiRequest<Envelope<Record<string, unknown>>>(
    `/v1/admin/products/${productId}/context-assignments`,
    { method: "POST", body: payload },
  );
  return response.data;
}

export async function bulkAssignProducts(payload: Record<string, unknown>) {
  const response = await apiRequest<
    Envelope<{ affected: number; queued: boolean }>
  >("/v1/admin/recommendations/bulk-assignments", {
    method: "POST",
    body: payload,
  });
  return response.data;
}
