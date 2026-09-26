const SENSITIVE = [
  "lng",
  "lat",
  "longitude",
  "latitude",
  "pin",
  "accuracy",
  "coordinate",
  "coordinates",
  "permit",
  "license",
];

export const RECOMMENDATION_EVENTS = {
  section_viewed: "recommendation_section_viewed",
  product_viewed: "recommendation_product_viewed",
  explanation_opened: "recommendation_explanation_opened",
  clicked: "recommendation_clicked",
  added_to_cart: "recommendation_added_to_cart",
  zero_result: "recommendation_zero_result",
  blocked: "recommendation_blocked_by_legal_gate",
  general_catalog: "recommendation_general_catalog_opened",
} as const;

export type RecommendationEventName =
  (typeof RECOMMENDATION_EVENTS)[keyof typeof RECOMMENDATION_EVENTS];

export type RecommendationEventPayload = Record<
  string,
  string | number | boolean | null | undefined
>;

export function sanitizeRecommendationEvent(
  payload: RecommendationEventPayload,
): RecommendationEventPayload {
  const safe: RecommendationEventPayload = {};
  for (const [key, value] of Object.entries(payload)) {
    if (SENSITIVE.includes(key.toLowerCase())) continue;
    if (typeof value === "string" && /^-?\d+\.\d+,-?\d+\.\d+$/.test(value)) {
      continue;
    }
    safe[key] = value;
  }
  return safe;
}

/** No-op until an analytics provider is attached. Payloads are stripped of location. */
export function trackRecommendationEvent(
  name: RecommendationEventName,
  payload: RecommendationEventPayload = {},
): void {
  void name;
  void sanitizeRecommendationEvent(payload);
}
