import type { BBox } from "@/features/map/lib/bbox";
import { spanDegrees } from "@/features/map/lib/bbox";

export type MapDetailLevel = "region" | "local" | "full";

/** Country detail omits geometry, so the map never requests it for display. */
export function detailLevelForZoom(zoom: number): MapDetailLevel {
  if (zoom >= 12) return "full";
  if (zoom >= 9) return "local";
  return "region";
}

export function viewportExceedsDetail(
  detail: MapDetailLevel,
  bbox: BBox,
): boolean {
  const span = spanDegrees(bbox);
  if (detail === "full") return span > 2;
  return span > 60;
}
