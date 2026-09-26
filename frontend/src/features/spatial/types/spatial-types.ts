export type SpatialSridMeta = {
  srid: 4326;
  coordinate_order: "longitude,latitude";
};

export type GeoJsonPosition = [number, number];

export type GeoJsonPolygon = {
  type: "Polygon";
  coordinates: GeoJsonPosition[][];
};

export type GeoJsonMultiPolygon = {
  type: "MultiPolygon";
  coordinates: GeoJsonPosition[][][];
};

export type SpatialGeometry = GeoJsonPolygon | GeoJsonMultiPolygon;

export type SpatialAttribution = {
  source_name?: string | null;
  publisher_name?: string | null;
  license_name?: string | null;
  attribution_text?: string | null;
  official_url?: string | null;
  verified_at?: string | null;
};

export type BoundaryClassification = {
  relation: "inside" | "outside" | "on_boundary";
  near_boundary: boolean;
  boundary_distance_degrees: number;
  warning_tolerance_degrees: number;
  longitude: number;
  latitude: number;
} & SpatialSridMeta;

export type SpatialLegalOutcome =
  "allowed" | "prohibited" | "conditional" | "unknown" | "conflict";

export type ZoneSummary = {
  id: string;
  slug?: string;
  name: string;
  official_name: string;
  zone_type: string;
  legal_state?: SpatialLegalOutcome;
  region_code?: string | null;
  is_fictional: boolean;
};

export type ZoneMatch = {
  zone: ZoneSummary;
  classification: BoundaryClassification;
  attribution?: SpatialAttribution;
  last_verified_at?: string | null;
} & SpatialSridMeta;

export type ViewportFeature = {
  type: "Feature";
  id: string;
  properties: ZoneSummary & {
    attribution?: SpatialAttribution;
    last_verified_at?: string | null;
    effective_from?: string;
  } & SpatialSridMeta;
  geometry: SpatialGeometry | null;
  bbox: [number, number, number, number];
};

export type ViewportResponse = {
  type: "FeatureCollection";
  crs: { type: "name"; properties: { name: string } };
  coordinate_order: "longitude,latitude";
  features: ViewportFeature[];
  generated_at?: string;
  detail?: string;
  geometry_included?: boolean;
  disclaimer?: string;
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export type ZoneDetails = {
  id: string;
  name: string;
  official_name: string;
  zone_type: string;
  is_fictional: boolean;
  attribution: SpatialAttribution;
  last_verified_at?: string | null;
  disclaimer: string;
  precision_note: string;
  slug?: string;
  short_description?: string | null;
  legal_state?: SpatialLegalOutcome;
  region_code?: string | null;
  dataset_name?: string | null;
  dataset_version_id?: string | null;
  geometry_version_id?: string | null;
  effective_from?: string | null;
  effective_until?: string | null;
  assignments: Array<{
    id: string;
    assignment_type: string;
    precedence: number;
    rule: {
      id: string;
      title: string;
      effect: string;
      activity_type?: string;
      interpretation_summary?: string | null;
    } | null;
  }>;
  geometry: SpatialGeometry | null;
  bounding_box?: {
    west: number;
    south: number;
    east: number;
    north: number;
    srid: number;
  };
} & SpatialSridMeta;

export type SpatialEvaluation = {
  coordinate: { longitude: number; latitude: number } & SpatialSridMeta;
  outcome: SpatialLegalOutcome;
  boundary_warning: boolean;
  on_boundary: boolean;
  near_boundary: boolean;
  matching_zones: ZoneMatch[];
  applied_rules: Array<Record<string, unknown>>;
  seasonal_availability?: {
    results?: Array<{
      species?: {
        slug?: string;
        common_name?: string | null;
        scientific_name?: string;
      };
      overall_state?: string;
      timeline?: Array<{ from?: string; to?: string; state?: string }>;
      available_windows?: Array<{ from?: string; to?: string }>;
      conditional_windows?: Array<{ from?: string; to?: string }>;
      season_windows?: Array<{ from?: string; to?: string }>;
      conditions?: Array<{
        operator?: string;
        string_value?: string | null;
      }>;
      limits?: Array<{
        amount?: string | number | null;
        unit?: string | null;
      }>;
      next_opening?: { local_date?: string } | null;
      next_closing?: { local_end_date_inclusive?: string } | null;
      last_verified_at?: string | null;
    }>;
  } | null;
  conditions: Array<{
    type?: string;
    operator?: string;
    value?: string | null;
  }>;
  limits: Array<{
    type?: string;
    amount?: string | number | null;
    unit?: string | null;
    period?: string;
    applies_per?: string;
  }>;
  conflicts?: Array<{ id?: string; type?: string; severity?: string }>;
  citations: Array<{
    rule_id?: string;
    reference_code?: string | null;
    excerpt?: string | null;
    is_primary?: boolean;
    source_name?: string | null;
  }>;
  last_verified_at?: string | null;
  disclaimer: string;
  context_token?: string | null;
  activity_type?: string;
  occurred_at?: string;
  spatial_classification?: string;
};

export type SpatialSearchHit = {
  result_type: "spatial_zone";
  id: string;
  slug: string;
  name: string;
  official_name: string;
  zone_type: string;
  region_code?: string | null;
  bbox: [number, number, number, number];
};

export type SpatialSearchResponse = {
  results: SpatialSearchHit[];
  geocoder: "unavailable";
  disclaimer: string;
};
