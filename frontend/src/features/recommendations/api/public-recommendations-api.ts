import { apiRequest } from "@/lib/api-client";
import { recommendationRequestBody } from "@/features/recommendations/lib/request";
import type {
  RecommendationRequest,
  RecommendationResponse,
} from "@/features/recommendations/types/recommendation-types";

type Envelope<T> = { data: T };

export async function fetchContextualRecommendations(
  request: RecommendationRequest,
): Promise<RecommendationResponse> {
  const response = await apiRequest<Envelope<RecommendationResponse>>(
    "/v1/recommendations/contextual",
    { method: "POST", body: recommendationRequestBody(request) },
  );
  return response.data;
}
