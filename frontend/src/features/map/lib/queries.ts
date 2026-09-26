import type { BBox } from "@/features/map/lib/bbox";
import type { MapDetailLevel } from "@/features/map/lib/detail-level";
import { tbilisiNoon } from "@/features/map/lib/dates";
import type { ShareableMapState } from "@/features/map/lib/url-state";

export function buildViewportSearch(input: {
  bbox: BBox;
  detail: MapDetailLevel;
  state: ShareableMapState;
  types: string[];
  page: number;
}): string {
  const params = new URLSearchParams();
  params.set("bbox", input.bbox.join(","));
  params.set("detail", input.detail);
  params.set("activity", input.state.activity);
  params.set("per_page", "100");
  params.set("page", String(input.page));
  if (input.types.length > 0) params.set("types", input.types.join(","));
  const day = input.state.mode === "date" ? input.state.date : input.state.from;
  if (input.state.mode !== "any") params.set("at", tbilisiNoon(day));
  return `?${params.toString()}`;
}

export function buildEvaluationSearch(
  state: ShareableMapState,
  longitude: number,
  latitude: number,
): string {
  const params = new URLSearchParams();
  params.set("lng", longitude.toFixed(5));
  params.set("lat", latitude.toFixed(5));
  params.set("activity", state.activity);
  const day = state.mode === "date" ? state.date : state.from;
  params.set("at", tbilisiNoon(state.mode === "any" ? state.date : day));
  if (state.species) params.set("species_slug", state.species);
  if (state.mode === "date") {
    params.set("from", state.date);
    params.set("to", state.date);
    params.set("availability", "any_date");
  }
  if (state.mode === "range" || state.mode === "timeline") {
    params.set("from", state.from);
    params.set("to", state.to);
    params.set(
      "availability",
      state.mode === "timeline" ? "timeline" : "entire_period",
    );
  }
  return `?${params.toString()}`;
}
