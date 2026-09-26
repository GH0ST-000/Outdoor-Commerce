import type { SpatialAttribution } from "@/features/spatial/types/spatial-types";

export function formatAttribution(
  attribution: SpatialAttribution | null | undefined,
): string {
  if (!attribution) return "";
  const parts = [
    attribution.attribution_text,
    attribution.source_name,
    attribution.publisher_name,
    attribution.license_name,
  ].filter((part): part is string => Boolean(part && part.trim()));
  return [...new Set(parts)].join(" · ");
}

export function isSafeHttpUrl(value: string | null | undefined): value is string {
  if (!value) return false;
  try {
    const url = new URL(value);
    return url.protocol === "https:" || url.protocol === "http:";
  } catch {
    return false;
  }
}
