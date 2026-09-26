import {
  serializeMapState,
  type ShareableMapState,
} from "@/features/map/lib/url-state";

export function buildShareUrl(
  origin: string,
  pathname: string,
  state: ShareableMapState,
  includePin: boolean,
): string {
  const query = serializeMapState(state, { includePin });
  return `${origin}${pathname}${query ? `?${query}` : ""}`;
}

export function shareUrlContainsPin(url: string): boolean {
  return new URL(url, "http://localhost").searchParams.has("pin");
}
