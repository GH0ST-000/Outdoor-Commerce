import type { AvailabilityResult } from "@/features/seasons/api/public-seasons-api";

/**
 * Display boundaries are OCHA COD-AB Georgia admin level 2, reviewed 2024,
 * simplified for the map. They are a reference outline, not a cadastral line.
 * Source: https://data.humdata.org/dataset/cod-ab-geo
 */
const OFFICIAL_MUNICIPALITIES: Record<string, readonly string[]> = {
  Akhalkalaki: ["ახალქალაქი", "ახალქალაქის"],
  Ninotsminda: ["ნინოწმინდა", "ნინოწმინდის"],
  Tsalka: ["წალკა", "წალკის"],
  Dmanisi: ["დმანისი", "დმანისის"],
};

export type MunicipalityFeature = GeoJSON.Feature<
  GeoJSON.Polygon | GeoJSON.MultiPolygon,
  {
    id: string;
    name: string;
    name_en: string;
    kind: "municipality" | "city";
  }
>;

const EMPTY: GeoJSON.FeatureCollection = {
  type: "FeatureCollection",
  features: [],
};

function named(value: string, nameEn: string): boolean {
  return (OFFICIAL_MUNICIPALITIES[nameEn] ?? []).includes(value.trim());
}

export function seasonScopeCollection(
  features: MunicipalityFeature[],
  overallState: AvailabilityResult["overall_state"],
  conditions: AvailabilityResult["conditions"],
): GeoJSON.FeatureCollection {
  if (
    overallState === "closed" ||
    overallState === "unknown" ||
    overallState === "conflict"
  ) {
    return EMPTY;
  }

  const regions = conditions.filter(
    (condition) =>
      condition.condition_type === "region" &&
      typeof condition.string_value === "string" &&
      condition.string_value.trim() !== "",
  );
  const included = regions
    .filter((condition) => condition.operator !== "not_equals")
    .map((condition) => condition.string_value as string);
  const excluded = regions
    .filter((condition) => condition.operator === "not_equals")
    .map((condition) => condition.string_value as string);
  const municipalities = features.filter(
    (feature) => feature.properties.kind === "municipality",
  );

  let chosen = municipalities;
  if (included.length > 0) {
    chosen = municipalities.filter((feature) =>
      included.some((name) => named(name, feature.properties.name_en)),
    );
  } else if (excluded.length > 0) {
    chosen = municipalities.filter(
      (feature) =>
        !excluded.some((name) => named(name, feature.properties.name_en)),
    );
  }

  return {
    type: "FeatureCollection",
    features: chosen.map((feature) => ({
      ...feature,
      properties: {
        ...feature.properties,
        legal_state: "allowed",
      },
    })),
  };
}

export function featureCollectionBbox(
  collection: GeoJSON.FeatureCollection,
): [number, number, number, number] | undefined {
  let west = Infinity;
  let south = Infinity;
  let east = -Infinity;
  let north = -Infinity;
  const visit = (value: unknown) => {
    if (!Array.isArray(value)) return;
    if (typeof value[0] === "number" && typeof value[1] === "number") {
      west = Math.min(west, value[0]);
      east = Math.max(east, value[0]);
      south = Math.min(south, value[1]);
      north = Math.max(north, value[1]);
      return;
    }
    for (const child of value) visit(child);
  };
  for (const feature of collection.features) {
    const geometry = feature.geometry as { coordinates?: unknown } | null;
    if (geometry && "coordinates" in geometry) visit(geometry.coordinates);
  }
  if (!Number.isFinite(west)) return undefined;
  return [west, south, east, north];
}
