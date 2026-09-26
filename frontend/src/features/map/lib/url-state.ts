import { roundCoordinate } from "@/features/map/lib/bbox";
import { LAYER_ORDER, type LayerId } from "@/features/map/lib/cartography";
import type { SpatialLegalOutcome } from "@/features/spatial/types/spatial-types";

export type MapActivity = "hunting" | "fishing";
export type MapAvailabilityMode = "date" | "range" | "any" | "timeline";
export type MapViewMode = "map" | "list" | "split";

const ACTIVITIES = new Set<MapActivity>(["hunting", "fishing"]);
const MODES = new Set<MapAvailabilityMode>(["date", "range", "any", "timeline"]);
const VIEWS = new Set<MapViewMode>(["map", "list", "split"]);
const STATES = new Set<SpatialLegalOutcome>([
  "allowed",
  "prohibited",
  "conditional",
  "unknown",
  "conflict",
]);
const DATE = /^\d{4}-\d{2}-\d{2}$/;
const UUID =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export type ShareableMapState = {
  activity: MapActivity;
  mode: MapAvailabilityMode;
  date: string;
  from: string;
  to: string;
  species: string | null;
  layers: LayerId[];
  states: SpatialLegalOutcome[];
  lng: number;
  lat: number;
  z: number;
  zone: string | null;
  pin: { lng: number; lat: number } | null;
  view: MapViewMode;
};

export type MapUrlDefaults = {
  date: string;
  lng: number;
  lat: number;
  z: number;
};

export function defaultMapState(defaults: MapUrlDefaults): ShareableMapState {
  return {
    activity: "hunting",
    mode: "date",
    date: defaults.date,
    from: defaults.date,
    to: defaults.date,
    species: null,
    layers: ["protected", "hunting", "special", "wildlife"],
    states: [],
    lng: roundCoordinate(defaults.lng, 2),
    lat: roundCoordinate(defaults.lat, 2),
    z: roundCoordinate(defaults.z, 1),
    zone: null,
    pin: null,
    view: "map",
  };
}

function list<T extends string>(raw: string | null, allowed: Set<T>): T[] {
  if (!raw) return [];
  return raw
    .split(",")
    .map((item) => item.trim())
    .filter((item): item is T => allowed.has(item as T));
}

function finite(raw: string | null): number | null {
  if (raw === null || raw.trim() === "") return null;
  const value = Number(raw);
  return Number.isFinite(value) ? value : null;
}

export function parseMapSearchParams(
  params: URLSearchParams,
  defaults: MapUrlDefaults,
): ShareableMapState {
  const base = defaultMapState(defaults);
  const activity = params.get("activity");
  if (activity && ACTIVITIES.has(activity as MapActivity)) {
    base.activity = activity as MapActivity;
  }
  const mode = params.get("mode");
  if (mode && MODES.has(mode as MapAvailabilityMode)) {
    base.mode = mode as MapAvailabilityMode;
  }
  const date = params.get("date");
  if (date && DATE.test(date)) base.date = date;
  const from = params.get("from");
  const to = params.get("to");
  if (from && DATE.test(from)) base.from = from;
  if (to && DATE.test(to) && base.from <= to) base.to = to;
  const species = params.get("species");
  if (species && /^[a-z0-9-]{2,160}$/i.test(species)) base.species = species;
  const layers = list(params.get("layers"), new Set(LAYER_ORDER));
  if (layers.length > 0) base.layers = layers;
  base.states = list(params.get("states"), STATES);
  const lng = finite(params.get("lng"));
  const lat = finite(params.get("lat"));
  const zoom = finite(params.get("z"));
  if (lng !== null && lng >= -180 && lng <= 180) {
    base.lng = roundCoordinate(lng, 2);
  }
  if (lat !== null && lat >= -90 && lat <= 90) {
    base.lat = roundCoordinate(lat, 2);
  }
  if (zoom !== null && zoom >= 0 && zoom <= 22) {
    base.z = roundCoordinate(zoom, 1);
  }
  const zone = params.get("zone");
  if (zone && UUID.test(zone)) base.zone = zone.toLowerCase();
  const pin = params.get("pin");
  if (pin) {
    const [pinLng, pinLat] = pin.split(",").map((part) => Number(part.trim()));
    if (
      Number.isFinite(pinLng) &&
      Number.isFinite(pinLat) &&
      pinLng >= -180 &&
      pinLng <= 180 &&
      pinLat >= -90 &&
      pinLat <= 90
    ) {
      base.pin = {
        lng: roundCoordinate(pinLng, 4),
        lat: roundCoordinate(pinLat, 4),
      };
    }
  }
  const view = params.get("view");
  if (view && VIEWS.has(view as MapViewMode)) base.view = view as MapViewMode;
  if (base.activity === "fishing" && !base.layers.includes("fishing")) {
    base.layers = [...base.layers, "fishing"];
  }
  return base;
}

export function serializeMapState(
  state: ShareableMapState,
  options: { includePin?: boolean } = {},
): string {
  const params = new URLSearchParams();
  params.set("activity", state.activity);
  params.set("mode", state.mode);
  if (state.mode === "date") params.set("date", state.date);
  if (state.mode === "range" || state.mode === "timeline") {
    params.set("from", state.from);
    params.set("to", state.to);
  }
  if (state.species) params.set("species", state.species);
  params.set("layers", state.layers.join(","));
  if (state.states.length > 0) params.set("states", state.states.join(","));
  params.set("lng", state.lng.toFixed(2));
  params.set("lat", state.lat.toFixed(2));
  params.set("z", state.z.toFixed(1));
  if (state.zone) params.set("zone", state.zone);
  if (options.includePin && state.pin) {
    params.set("pin", `${state.pin.lng.toFixed(4)},${state.pin.lat.toFixed(4)}`);
  }
  if (state.view !== "map") params.set("view", state.view);
  return params.toString();
}

export function compatibleSpeciesActivity(
  activity: MapActivity,
  speciesActivity: string | null | undefined,
): boolean {
  if (!speciesActivity) return true;
  return speciesActivity === activity || speciesActivity === "both";
}
