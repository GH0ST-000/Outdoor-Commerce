import type { SpatialLegalOutcome } from "@/features/spatial/types/spatial-types";

/**
 * Paint values mirror storefront tokens in styles/tokens/primitives.css.
 * MapLibre paint cannot read CSS variables, so the hex values live here once.
 */
export const mapPalette = {
  prohibited: { token: "--destructive", fill: "#b8443c", line: "#7a241e" },
  conditional: { token: "--sand", fill: "#c4a484", line: "#8a6240" },
  allowed: { token: "--olive", fill: "#556b2f", line: "#2f3d18" },
  unknown: { token: "--mist", fill: "#e5e5e5", line: "#5c564e" },
  conflict: { token: "--copper", fill: "#c45e32", line: "#1a1613" },
  protected: { token: "--olive", fill: "#556b2f", line: "#2c3a16" },
  selected: { token: "--warm-bone", fill: "#f3ece3", line: "#f3ece3" },
  hover: { token: "--sand", fill: "#c4a484", line: "#f3ece3" },
  admin: { token: "--river", fill: "#5b7380", line: "#5b7380" },
  user: { token: "--river", fill: "#5b7380", line: "#1a1a1a" },
} as const;

export type LegalPaint = {
  fill: string;
  line: string;
  opacity: number;
  dash: number[];
  mark: string;
};

/** Category color only. Not a legal outcome. */
export const protectedCategoryPaint = {
  national_park: { fill: "#1f6b4a", line: "#0f3d28" },
  strict_nature_reserve: { fill: "#1a4f8b", line: "#0d2c52" },
  managed_reserve: { fill: "#8a5a2b", line: "#5c3a16" },
  natural_monument: { fill: "#7a3e5c", line: "#4c2438" },
  protected_area: { fill: "#2f6f6a", line: "#184440" },
} as const;

export const PROTECTED_CATEGORY_ORDER = [
  "national_park",
  "strict_nature_reserve",
  "managed_reserve",
  "natural_monument",
  "protected_area",
] as const;

/** Both official texts prohibit hunting inside these categories. The buffer is not drawn. */
export const HUNTING_INTERIOR_PROHIBITED = [
  "national_park",
  "strict_nature_reserve",
] as const;

export function displayLegalState(
  zoneType: string,
  activity: string,
  legalState: string,
): string {
  if (
    legalState === "unknown" &&
    activity === "hunting" &&
    (HUNTING_INTERIOR_PROHIBITED as readonly string[]).includes(zoneType)
  ) {
    return "prohibited";
  }

  return legalState;
}

export function paintForLegalState(state: string): LegalPaint {
  switch (state as SpatialLegalOutcome) {
    case "prohibited":
      return { ...mapPalette.prohibited, opacity: 0.42, dash: [1, 0], mark: "■" };
    case "conditional":
      return { ...mapPalette.conditional, opacity: 0.38, dash: [2, 2], mark: "▲" };
    case "allowed":
      return { ...mapPalette.allowed, opacity: 0.28, dash: [1, 0], mark: "●" };
    case "conflict":
      return { ...mapPalette.conflict, opacity: 0.4, dash: [1, 1.5], mark: "✕" };
    default:
      return { ...mapPalette.unknown, opacity: 0.22, dash: [0.5, 1.5], mark: "◌" };
  }
}

export const LAYER_ORDER = [
  "admin",
  "wildlife",
  "protected",
  "special",
  "fishing",
  "hunting",
] as const;

export type LayerId = (typeof LAYER_ORDER)[number];

export const LAYER_ZONE_TYPES: Record<LayerId, string[]> = {
  protected: [
    "protected_area",
    "national_park",
    "strict_nature_reserve",
    "managed_reserve",
    "natural_monument",
  ],
  hunting: ["hunting_restricted_area"],
  fishing: ["fishing_restricted_area"],
  special: ["special_regulation_zone"],
  wildlife: ["wildlife_management_area"],
  admin: ["administrative_region", "municipality"],
};

export function zoneTypesForLayers(layers: readonly LayerId[]): string[] {
  return layers.flatMap((layer) => LAYER_ZONE_TYPES[layer]);
}

export function layerForZoneType(zoneType: string): LayerId | null {
  for (const layer of LAYER_ORDER) {
    if (LAYER_ZONE_TYPES[layer].includes(zoneType)) return layer;
  }
  return null;
}
