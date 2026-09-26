import { describe, expect, it, vi } from "vitest";
import { sanitizeMapEventPayload } from "@/features/map/analytics/events";
import { formatAttribution } from "@/features/map/lib/attribution";
import { normalizeBbox, roundCoordinate } from "@/features/map/lib/bbox";
import {
  PROTECTED_CATEGORY_ORDER,
  displayLegalState,
  paintForLegalState,
  protectedCategoryPaint,
  zoneTypesForLayers,
} from "@/features/map/lib/cartography";
import {
  addCalendarDays,
  monthRange,
  weekRange,
} from "@/features/map/lib/dates";
import {
  detailLevelForZoom,
  viewportExceedsDetail,
} from "@/features/map/lib/detail-level";
import { resolveMapConfig } from "@/features/map/lib/map-config";
import {
  createViewportSession,
  viewportRequestKey,
} from "@/features/map/lib/request-key";
import {
  buildShareUrl,
  shareUrlContainsPin,
} from "@/features/map/lib/share-link";
import {
  compatibleSpeciesActivity,
  defaultMapState,
  parseMapSearchParams,
  serializeMapState,
} from "@/features/map/lib/url-state";

const defaults = { date: "2026-09-25", lng: 43.5, lat: 42.2, z: 6.5 };

describe("map url state", () => {
  it("ignores unsupported parameters and keeps a deterministic share link", () => {
    const parsed = parseMapSearchParams(
      new URLSearchParams(
        "activity=flying&mode=date&date=2026-09-25&layers=nope,protected&z=99",
      ),
      defaults,
    );
    expect(parsed.activity).toBe("hunting");
    expect(parsed.layers).toEqual(["protected"]);
    expect(parsed.z).toBe(6.5);
    const again = parseMapSearchParams(
      new URLSearchParams(serializeMapState(parsed)),
      defaults,
    );
    expect(serializeMapState(again)).toBe(serializeMapState(parsed));
  });

  it("excludes a pin unless sharing was explicit", () => {
    const state = {
      ...defaultMapState(defaults),
      pin: { lng: 44.12345, lat: 41.98765 },
    };
    const ordinary = buildShareUrl("https://hunt.test", "/map", state, false);
    const shared = buildShareUrl("https://hunt.test", "/map", state, true);
    expect(shareUrlContainsPin(ordinary)).toBe(false);
    expect(shareUrlContainsPin(shared)).toBe(true);
    expect(shared).toContain("pin=44.1234%2C41.9877");
  });

  it("rounds viewport coordinates and rejects an inverted box", () => {
    expect(roundCoordinate(44.12349, 2)).toBe(44.12);
    expect(normalizeBbox(46, 41, 43, 42)).toBeNull();
    expect(normalizeBbox(43.1234, 41.1, 44.2, 42.2)).toEqual([
      43.123, 41.1, 44.2, 42.2,
    ]);
  });

  it("selects detail from zoom and blocks oversized views", () => {
    expect(detailLevelForZoom(6)).toBe("region");
    expect(detailLevelForZoom(10)).toBe("local");
    expect(detailLevelForZoom(13)).toBe("full");
    expect(viewportExceedsDetail("full", [40, 41, 44, 43])).toBe(true);
    expect(viewportExceedsDetail("region", [43, 41, 45, 42])).toBe(false);
    expect(viewportExceedsDetail("region", [39, 41, 47, 44])).toBe(false);
    expect(viewportExceedsDetail("region", [10, 20, 80, 55])).toBe(true);
  });

  it("maps legal states and layer filters without color alone", () => {
    expect(paintForLegalState("unknown").mark).not.toBe(
      paintForLegalState("prohibited").mark,
    );
    expect(paintForLegalState("conflict").dash).not.toEqual(
      paintForLegalState("prohibited").dash,
    );
    expect(zoneTypesForLayers(["hunting", "protected"])).toContain(
      "hunting_restricted_area",
    );
    const fills = PROTECTED_CATEGORY_ORDER.map(
      (zoneType) => protectedCategoryPaint[zoneType].fill,
    );
    expect(new Set(fills).size).toBe(fills.length);
    expect(fills).not.toContain(paintForLegalState("allowed").fill);
    expect(fills).not.toContain(paintForLegalState("prohibited").fill);
    expect(displayLegalState("national_park", "hunting", "unknown")).toBe(
      "prohibited",
    );
    expect(
      displayLegalState("strict_nature_reserve", "hunting", "unknown"),
    ).toBe("prohibited");
    expect(displayLegalState("managed_reserve", "hunting", "unknown")).toBe(
      "unknown",
    );
    expect(displayLegalState("national_park", "fishing", "unknown")).toBe(
      "unknown",
    );
  });

  it("serializes periods and drops an incompatible species", () => {
    const state = {
      ...defaultMapState(defaults),
      mode: "range" as const,
      from: "2026-09-01",
      to: "2026-09-07",
    };
    expect(serializeMapState(state)).toContain("from=2026-09-01");
    expect(compatibleSpeciesActivity("fishing", "hunting")).toBe(false);
    expect(compatibleSpeciesActivity("hunting", "hunting")).toBe(true);
  });

  it("rejects a stale viewport response and deduplicates keys", () => {
    const session = createViewportSession();
    const key = viewportRequestKey({
      bbox: [43, 41, 44, 42],
      detail: "region",
      activity: "hunting",
      types: ["protected_area"],
      at: null,
      page: 1,
      locale: "ka",
    });
    const first = session.begin(key);
    const duplicate = session.begin(key);
    expect(duplicate.skip).toBe(true);
    const next = session.begin(`${key}|next`);
    expect(session.isCurrent(first.seq)).toBe(false);
    expect(session.isCurrent(next.seq)).toBe(true);
    expect(first.signal.aborted).toBe(true);
  });

  it("formats attribution and strips coordinates from analytics", () => {
    expect(
      formatAttribution({
        source_name: "Agency",
        attribution_text: "Agency",
        publisher_name: null,
      }),
    ).toBe("Agency");
    expect(
      sanitizeMapEventPayload({ outcome: "unknown", lng: 44.1, pin: "1,2" }),
    ).toEqual({
      outcome: "unknown",
    });
  });

  it("uses a development basemap only outside production", () => {
    vi.stubEnv("NEXT_PUBLIC_MAP_STYLE_URL", "");
    expect(resolveMapConfig("test").styleMode).toBe("development-fallback");
    expect(resolveMapConfig("production").styleMode).toBe("unconfigured");
    vi.unstubAllEnvs();
  });

  it("builds calendar ranges in civil dates", () => {
    expect(addCalendarDays("2026-09-25", 6)).toBe("2026-10-01");
    expect(weekRange("2026-09-25")).toEqual({
      from: "2026-09-21",
      to: "2026-09-27",
    });
    expect(monthRange("2026-09-25").from).toBe("2026-09-01");
  });
});
