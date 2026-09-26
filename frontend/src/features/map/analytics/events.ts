const SENSITIVE = ["lng", "lat", "longitude", "latitude", "pin", "accuracy", "coordinate"];

export const MAP_EVENTS = {
  map_opened: "map_opened",
  activity_changed: "activity_changed",
  filter_applied: "filter_applied",
  zone_opened: "zone_opened",
  location_checker_initiated: "location_checker_initiated",
  location_permission: "location_permission",
  evaluation_completed: "evaluation_completed",
  official_source_opened: "official_source_opened",
  share_link_created: "share_link_created",
  view_mode_changed: "view_mode_changed",
} as const;

export type MapEventName = (typeof MAP_EVENTS)[keyof typeof MAP_EVENTS];

export type MapEventPayload = Record<string, string | number | boolean | null | undefined>;

export function sanitizeMapEventPayload(payload: MapEventPayload): MapEventPayload {
  const safe: MapEventPayload = {};
  for (const [key, value] of Object.entries(payload)) {
    if (SENSITIVE.includes(key.toLowerCase())) continue;
    if (typeof value === "string" && /^-?\d+\.\d+,-?\d+\.\d+$/.test(value)) continue;
    safe[key] = value;
  }
  return safe;
}

/** Same privacy contract as storefront analytics: a no-op until a provider is attached. */
export function trackMapEvent(name: MapEventName, payload: MapEventPayload = {}): void {
  void name;
  void sanitizeMapEventPayload(payload);
}
