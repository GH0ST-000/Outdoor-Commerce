import { getPublicEnv } from "@/lib/env";

export const DEVELOPMENT_STYLE_URL =
  "https://tiles.openfreemap.org/styles/liberty";

export const LEGAL_TIME_ZONE = "Asia/Tbilisi";

export type MapConfig = {
  styleUrl: string | null;
  styleMode: "configured" | "development-fallback" | "unconfigured";
  token: string | null;
  center: [number, number];
  zoom: number;
  minZoom: number;
  maxZoom: number;
  bounds: [number, number, number, number];
};

function numberOr(value: string | undefined, fallback: number): number {
  if (value === undefined || value.trim() === "") return fallback;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function boundsOr(
  value: string | undefined,
  fallback: [number, number, number, number],
): [number, number, number, number] {
  if (!value) return fallback;
  const parts = value.split(",").map((part) => Number(part.trim()));
  if (parts.length !== 4 || parts.some((part) => !Number.isFinite(part))) {
    return fallback;
  }
  const [west, south, east, north] = parts;
  if (west >= east || south >= north) return fallback;
  return [west, south, east, north];
}

export function resolveMapConfig(nodeEnv = process.env.NODE_ENV): MapConfig {
  const env = getPublicEnv();
  const configured = env.NEXT_PUBLIC_MAP_STYLE_URL;
  const styleMode = configured
    ? "configured"
    : nodeEnv === "production"
      ? "unconfigured"
      : "development-fallback";

  return {
    styleUrl:
      styleMode === "configured"
        ? configured!
        : styleMode === "development-fallback"
          ? DEVELOPMENT_STYLE_URL
          : null,
    styleMode,
    token: env.NEXT_PUBLIC_MAP_TOKEN ?? null,
    center: [
      numberOr(env.NEXT_PUBLIC_MAP_CENTER_LNG, 43.5),
      numberOr(env.NEXT_PUBLIC_MAP_CENTER_LAT, 42.2),
    ],
    zoom: numberOr(env.NEXT_PUBLIC_MAP_ZOOM, 6.5),
    minZoom: numberOr(env.NEXT_PUBLIC_MAP_MIN_ZOOM, 5),
    maxZoom: numberOr(env.NEXT_PUBLIC_MAP_MAX_ZOOM, 16),
    bounds: boundsOr(env.NEXT_PUBLIC_MAP_BOUNDS, [39.9, 41.0, 46.8, 43.6]),
  };
}

export function styleUrlWithToken(
  styleUrl: string,
  token: string | null,
): string {
  if (!token) return styleUrl;
  return styleUrl.replaceAll("{token}", encodeURIComponent(token));
}
