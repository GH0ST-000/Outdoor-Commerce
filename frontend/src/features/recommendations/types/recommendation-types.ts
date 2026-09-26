export type RecommendationPlacement =
  | "outdoor_context_result"
  | "species_detail"
  | "season_explorer"
  | "map_location_result";

export type RecommendationLocale = "ka" | "en";

export type RecommendationGate =
  | "recommendations_allowed"
  | "recommendations_information_only"
  | "recommendations_blocked"
  | "recommendations_unknown";

export type RecommendationConfidence =
  "high" | "medium" | "low" | "insufficient";

export type RecommendationReason = {
  code: string;
  text: string;
};

export type RecommendationVariant = {
  variant_id: number;
  sku: string;
  in_stock: boolean;
  stock_status: string;
  price: {
    amount_minor: number;
    final_amount_minor: number;
    currency: string;
  } | null;
};

export type RecommendationCard = {
  slug: string;
  name: string;
  brand: string | null;
  primary_image: string | null;
  category: string | null;
  price: { amount_minor: number; currency: string } | null;
  sale_price: { amount_minor: number; currency: string } | null;
  currency: string;
  stock_status: string;
  eligible_variants: RecommendationVariant[];
  recommended_variant: string | null;
  score_band?: string;
  confidence: RecommendationConfidence;
  primary_reason: string;
  primary_reason_text: string;
  supporting_reasons: RecommendationReason[];
  warnings: RecommendationReason[];
  is_promoted: boolean;
  promotion_label: string | null;
  product_url: string;
  add_to_cart_eligible: boolean;
};

export type RecommendationResponse = {
  context: {
    activity: string;
    species_slug: string | null;
    species_category_code: string | null;
    zone_public_ids: string[];
    completeness: string;
    framing: string;
  };
  placement: RecommendationPlacement;
  gate: RecommendationGate;
  profile_version: number | null;
  recommendations: RecommendationCard[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    has_more: boolean;
  };
  warnings: RecommendationReason[];
  disclaimer: string;
};

export type RecommendationRequest = {
  placement: RecommendationPlacement;
  locale: RecommendationLocale;
  contextToken?: string | null;
  activity?: "hunting" | "fishing" | null;
  speciesSlug?: string | null;
  speciesCategoryCode?: string | null;
  zonePublicIds?: string[];
  periodFrom?: string | null;
  periodTo?: string | null;
  seasonPhase?: string | null;
  regionCode?: string | null;
  page?: number;
  currency?: string;
};
