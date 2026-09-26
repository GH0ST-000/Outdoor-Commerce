"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useLocale } from "@/components/locale-provider";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { getMapCopy } from "@/features/map/copy/map-copy";
import { MAP_EVENTS, trackMapEvent } from "@/features/map/analytics/events";
import { accuracyRing } from "@/features/map/lib/accuracy-ring";
import { normalizeBbox } from "@/features/map/lib/bbox";
import {
  displayLegalState,
  layerForZoneType,
  zoneTypesForLayers,
  type LayerId,
} from "@/features/map/lib/cartography";
import {
  addCalendarDays,
  monthRange,
  tbilisiToday,
  weekRange,
} from "@/features/map/lib/dates";
import {
  detailLevelForZoom,
  viewportExceedsDetail,
} from "@/features/map/lib/detail-level";
import { resolveMapConfig } from "@/features/map/lib/map-config";
import {
  buildEvaluationSearch,
  buildViewportSearch,
} from "@/features/map/lib/queries";
import {
  createViewportSession,
  viewportRequestKey,
} from "@/features/map/lib/request-key";
import { buildShareUrl } from "@/features/map/lib/share-link";
import {
  seasonScopeCollection,
  type MunicipalityFeature,
} from "@/features/map/lib/season-scope";
import {
  defaultMapState,
  parseMapSearchParams,
  serializeMapState,
  type MapViewMode,
  type ShareableMapState,
} from "@/features/map/lib/url-state";
import {
  evaluateCoordinate,
  fetchViewportZones,
  fetchZoneDetails,
  searchSpatialZones,
} from "@/features/spatial/api/public-spatial-api";
import type {
  SpatialEvaluation,
  SpatialSearchHit,
  ViewportFeature,
  ZoneDetails,
} from "@/features/spatial/types/spatial-types";
import { RecommendationSection } from "@/features/recommendations/components/RecommendationSection";
import {
  fetchAvailability,
  type AvailabilityResult,
} from "@/features/seasons/api/public-seasons-api";
import { getPublicSpeciesList } from "@/features/species/api/public-species-client";
import type { SpeciesCard } from "@/features/species/types/species-types";
import {
  MapCanvas,
  flyToZone,
  type MapFeatureClick,
} from "@/features/map/components/MapCanvas";
import {
  LayerControl,
  LocationResult,
  MapLegend,
  ResultsList,
  ShareDialog,
  ZoneDetail,
} from "@/features/map/components/MapPanels";
import type { Map as MapLibreMap } from "maplibre-gl";

type OverlayStatus =
  | "idle"
  | "loading"
  | "ready"
  | "error"
  | "zoom"
  | "hidden"
  | "empty"
  | "incomplete";

function readInitial(defaults: {
  date: string;
  lng: number;
  lat: number;
  z: number;
}) {
  if (typeof window === "undefined")
    return { state: defaultMapState(defaults), ignored: false };
  const parsed = parseMapSearchParams(
    new URLSearchParams(window.location.search),
    defaults,
  );
  const raw = new URLSearchParams(window.location.search);
  const ignored = ["activity", "mode", "view", "zone", "pin"].some((key) => {
    const value = raw.get(key);
    return (
      Boolean(value) &&
      serializeMapState(parsed, { includePin: true }).indexOf(`${key}=`) ===
        -1 &&
      key !== "pin"
    );
  });
  return { state: parsed, ignored };
}

export function LegalMapExperience() {
  const { locale } = useLocale();
  const copy = getMapCopy(locale);
  const config = useMemo(() => resolveMapConfig(), []);
  const router = useRouter();
  const pathname = usePathname();
  const defaults = useMemo(
    () => ({
      date: tbilisiToday(),
      lng: config.center[0],
      lat: config.center[1],
      z: config.zoom,
    }),
    [config],
  );
  const initial = useMemo(() => readInitial(defaults), [defaults]);
  const [state, setState] = useState<ShareableMapState>(initial.state);
  const ignoredLink = initial.ignored;
  const [features, setFeatures] = useState<ViewportFeature[]>([]);
  const [generatedAt, setGeneratedAt] = useState<string | null>(null);
  const [overlay, setOverlay] = useState<OverlayStatus>("idle");
  const [stale, setStale] = useState(false);
  const [offline, setOffline] = useState(
    () => typeof navigator !== "undefined" && navigator.onLine === false,
  );
  const [mapFailed, setMapFailed] = useState(false);
  const [evaluation, setEvaluation] = useState<SpatialEvaluation | null>(null);
  const [evaluating, setEvaluating] = useState(false);
  const [zone, setZone] = useState<ZoneDetails | null>(null);
  const [hover, setHover] = useState<{
    id: string;
    name: string;
    x: number;
    y: number;
  } | null>(null);
  const [copied, setCopied] = useState(false);
  const [legendOpen, setLegendOpen] = useState(true);
  const [userFix, setUserFix] = useState<{
    longitude: number;
    latitude: number;
    accuracy: number;
  } | null>(null);
  const [geoMessage, setGeoMessage] = useState<string | null>(null);
  const [geoAsked, setGeoAsked] = useState(false);
  const [speciesCatalog, setSpeciesCatalog] = useState<SpeciesCard[]>([]);
  const [seasonHit, setSeasonHit] = useState<AvailabilityResult | null>(null);
  const [municipalities, setMunicipalities] = useState<MunicipalityFeature[]>(
    [],
  );
  const [zoneHits, setZoneHits] = useState<SpatialSearchHit[]>([]);
  const [query, setQuery] = useState("");
  const [shareOpen, setShareOpen] = useState(false);
  const [shareFailed, setShareFailed] = useState(false);
  const [manualLng, setManualLng] = useState("");
  const [manualLat, setManualLat] = useState("");
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [overlaps, setOverlaps] = useState<string[]>([]);
  const [snap, setSnap] = useState<"collapsed" | "medium" | "expanded">(
    () => (initial.state.species ? "expanded" : "medium"),
  );
  const mapRef = useRef<MapLibreMap | null>(null);
  const moveTimer = useRef<number | null>(null);
  const urlTimer = useRef<number | null>(null);
  const sessionRef = useRef(createViewportSession());
  const etags = useRef(new Map<string, string>());
  const stateRef = useRef(state);
  useEffect(() => {
    stateRef.current = state;
  });

  const syncUrl = useCallback(
    (
      next: ShareableMapState,
      history: "push" | "replace",
      includePin = false,
    ) => {
      const queryString = serializeMapState(next, { includePin });
      const current = window.location.search.replace(/^\?/, "");
      if (queryString === current) return;
      const url = `${pathname}?${queryString}`;
      if (history === "push") router.push(url, { scroll: false });
      else router.replace(url, { scroll: false });
    },
    [pathname, router],
  );

  const loadViewport = useCallback(
    async (view: {
      west: number;
      south: number;
      east: number;
      north: number;
      zoom: number;
    }) => {
      const current = stateRef.current;
      if (current.layers.length === 0) {
        setOverlay("hidden");
        return;
      }
      const bbox = normalizeBbox(view.west, view.south, view.east, view.north);
      if (!bbox) {
        setOverlay("zoom");
        return;
      }
      const detail = detailLevelForZoom(view.zoom);
      if (viewportExceedsDetail(detail, bbox)) {
        setOverlay("zoom");
        return;
      }
      const types = zoneTypesForLayers(current.layers);
      const key = viewportRequestKey({
        bbox,
        detail,
        activity: current.activity,
        types,
        at: current.mode === "any" ? null : current.date,
        page: 1,
        locale,
      });
      const begun = sessionRef.current.begin(key);
      if (begun.skip) return;
      setOverlay("loading");
      try {
        const first = await fetchViewportZones(
          buildViewportSearch({ bbox, detail, state: current, types, page: 1 }),
          { locale, signal: begun.signal, ifNoneMatch: etags.current.get(key) },
        );
        if (!sessionRef.current.isCurrent(begun.seq)) return;
        if (first.status === 304) {
          setStale(false);
          setOverlay("ready");
          return;
        }
        const payload = first.data?.data;
        if (!payload) return;
        if (first.etag) etags.current.set(key, first.etag);
        let nextFeatures = payload.features;
        const incomplete =
          payload.pagination.last_page > 1 &&
          payload.pagination.total > nextFeatures.length;
        if (
          payload.pagination.last_page > 1 &&
          payload.pagination.total <= 100
        ) {
          const second = await fetchViewportZones(
            buildViewportSearch({
              bbox,
              detail,
              state: current,
              types,
              page: 2,
            }),
            { locale, signal: begun.signal },
          );
          if (!sessionRef.current.isCurrent(begun.seq)) return;
          nextFeatures = [
            ...nextFeatures,
            ...(second.data?.data.features ?? []),
          ];
        }
        setFeatures(nextFeatures);
        setGeneratedAt(payload.generated_at ?? null);
        setStale(false);
        setOverlay(
          nextFeatures.length === 0
            ? "empty"
            : incomplete
              ? "incomplete"
              : "ready",
        );
      } catch (error) {
        if (error instanceof DOMException && error.name === "AbortError")
          return;
        if (!sessionRef.current.isCurrent(begun.seq)) return;
        setStale(features.length > 0);
        setOverlay("error");
      }
    },
    [features.length, locale],
  );

  const update = useCallback(
    (
      patch: Partial<ShareableMapState>,
      history: "push" | "replace" = "replace",
    ) => {
      const current = stateRef.current;
      const next = { ...current, ...patch };
      if (patch.activity && patch.activity !== current.activity) {
        next.species = null;
        if (patch.activity === "fishing" && !next.layers.includes("fishing")) {
          next.layers = [...next.layers, "fishing"];
        }
        if (patch.activity === "hunting" && !next.layers.includes("hunting")) {
          next.layers = [...next.layers, "hunting"];
        }
        trackMapEvent(MAP_EVENTS.activity_changed, {
          activity: patch.activity,
        });
      }
      stateRef.current = next;
      setState(next);
      queueMicrotask(() => {
        syncUrl(next, history, false);
        if (
          patch.activity ||
          patch.layers ||
          patch.mode ||
          patch.date ||
          patch.from ||
          patch.to ||
          patch.species
        ) {
          const map = mapRef.current;
          if (map) {
            const bounds = map.getBounds();
            void loadViewport({
              west: bounds.getWest(),
              south: bounds.getSouth(),
              east: bounds.getEast(),
              north: bounds.getNorth(),
              zoom: map.getZoom(),
            });
          }
          if (
            (patch.activity ||
              patch.species ||
              patch.date ||
              patch.from ||
              patch.to ||
              patch.mode) &&
            next.pin
          ) {
            void evaluateCoordinate(
              buildEvaluationSearch(next, next.pin.lng, next.pin.lat),
              { locale },
            )
              .then((result) => {
                setEvaluation(result);
                trackMapEvent(MAP_EVENTS.evaluation_completed, {
                  outcome: result.outcome,
                });
              })
              .catch(() => setOverlay("error"));
          }
        }
      });
    },
    [loadViewport, locale, syncUrl],
  );

  useEffect(() => {
    const map = mapRef.current;
    map?.resize();
  }, [snap, state.view]);

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === "Escape") {
        setShareOpen(false);
        setFiltersOpen(false);
        setSnap("collapsed");
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    trackMapEvent(MAP_EVENTS.map_opened, { activity: state.activity });
    const onOffline = () => setOffline(true);
    const onOnline = () => setOffline(false);
    window.addEventListener("offline", onOffline);
    window.addEventListener("online", onOnline);
    const session = sessionRef.current;
    return () => {
      window.removeEventListener("offline", onOffline);
      window.removeEventListener("online", onOnline);
      session.abort();
      if (moveTimer.current !== null) window.clearTimeout(moveTimer.current);
      if (urlTimer.current !== null) window.clearTimeout(urlTimer.current);
    };
    // Opened once.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!state.zone) return;
    const controller = new AbortController();
    void fetchZoneDetails(state.zone, false, {
      locale,
      signal: controller.signal,
    })
      .then((details) => setZone(details))
      .catch(() => {
        if (!controller.signal.aborted) setZone(null);
      });
    return () => controller.abort();
  }, [locale, state.zone]);

  const evaluatePoint = useCallback(
    async (longitude: number, latitude: number, accuracy: number | null) => {
      setEvaluating(true);
      setEvaluation(null);
      update({ pin: { lng: longitude, lat: latitude } }, "replace");
      try {
        const result = await evaluateCoordinate(
          buildEvaluationSearch(stateRef.current, longitude, latitude),
          { locale },
        );
        setEvaluation(result);
        trackMapEvent(MAP_EVENTS.evaluation_completed, {
          outcome: result.outcome,
        });
        if (accuracy !== null && accuracy > 250)
          setGeoMessage(copy.locationLowAccuracy);
      } catch {
        setEvaluation(null);
        setOverlay("error");
      } finally {
        setEvaluating(false);
      }
    },
    [copy.locationLowAccuracy, locale, update],
  );

  const onMoveEnd = useCallback(
    (view: {
      west: number;
      south: number;
      east: number;
      north: number;
      zoom: number;
      lng: number;
      lat: number;
    }) => {
      if (moveTimer.current !== null) window.clearTimeout(moveTimer.current);
      moveTimer.current = window.setTimeout(() => {
        void loadViewport(view);
      }, 80);
      if (urlTimer.current !== null) window.clearTimeout(urlTimer.current);
      urlTimer.current = window.setTimeout(() => {
        update(
          {
            lng: Math.round(view.lng * 100) / 100,
            lat: Math.round(view.lat * 100) / 100,
            z: Math.round(view.zoom * 10) / 10,
          },
          "replace",
        );
      }, 700);
    },
    [loadViewport, update],
  );

  const onMapClick = useCallback(
    (event: MapFeatureClick) => {
      setOverlaps(event.ids);
      if (event.ids.length === 1) {
        update({ zone: event.ids[0] }, "push");
        trackMapEvent(MAP_EVENTS.zone_opened, { zone_type: "selected" });
      }
      void evaluatePoint(event.longitude, event.latitude, null);
      setSnap("expanded");
    },
    [evaluatePoint, update],
  );

  function useMyLocation() {
    setGeoAsked(true);
    trackMapEvent(MAP_EVENTS.location_checker_initiated, {});
    if (!navigator.geolocation) {
      setGeoMessage(copy.locationUnavailable);
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        trackMapEvent(MAP_EVENTS.location_permission, { result: "granted" });
        const next = {
          longitude: position.coords.longitude,
          latitude: position.coords.latitude,
          accuracy: position.coords.accuracy,
        };
        setUserFix(next);
        setGeoMessage(null);
        mapRef.current?.easeTo({
          center: [next.longitude, next.latitude],
          zoom: Math.max(state.z, 12),
        });
        void evaluatePoint(next.longitude, next.latitude, next.accuracy);
      },
      (error) => {
        trackMapEvent(MAP_EVENTS.location_permission, { result: "denied" });
        setGeoMessage(
          error.code === error.PERMISSION_DENIED
            ? copy.locationDenied
            : error.code === error.TIMEOUT
              ? copy.locationTimeout
              : copy.locationUnavailable,
        );
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
  }

  useEffect(() => {
    let cancelled = false;
    void fetch("/map/georgia-municipalities.geojson")
      .then((response) => response.json())
      .then((payload: { features?: MunicipalityFeature[] }) => {
        if (!cancelled) setMunicipalities(payload.features ?? []);
      })
      .catch(() => {
        if (!cancelled) setMunicipalities([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    void getPublicSpeciesList(
      {
        sort: "name",
        page: 1,
        activity_type: state.activity,
        per_page: 50,
      },
      locale === "en" ? "en" : "ka",
    )
      .then((result) => {
        if (!cancelled) setSpeciesCatalog(result.data);
      })
      .catch(() => {
        if (!cancelled) setSpeciesCatalog([]);
      });
    return () => {
      cancelled = true;
    };
  }, [locale, state.activity]);

  useEffect(() => {
    if (!state.species || state.mode === "any") {
      setSeasonHit(null);
      return;
    }
    const from = state.mode === "date" ? state.date : state.from;
    const to = state.mode === "date" ? state.date : state.to;
    let cancelled = false;
    void fetchAvailability(
      {
        activity: state.activity === "fishing" ? "fishing" : "hunting",
        from,
        to,
        mode: "any_date",
        species: state.species,
      },
      locale === "en" ? "en" : "ka",
    )
      .then((payload) => {
        if (cancelled) return;
        setSeasonHit(
          payload.results.find((row) => row.species.slug === state.species) ??
            payload.results[0] ??
            null,
        );
      })
      .catch(() => {
        if (!cancelled) setSeasonHit(null);
      });
    return () => {
      cancelled = true;
    };
  }, [
    locale,
    state.activity,
    state.species,
    state.mode,
    state.date,
    state.from,
    state.to,
  ]);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      const text = query.trim();
      if (text.length < 2) {
        setZoneHits([]);
        return;
      }
      const controller = new AbortController();
      void searchSpatialZones(`?q=${encodeURIComponent(text)}`, {
        locale,
        signal: controller.signal,
      })
        .then((result) => setZoneHits(result.results))
        .catch(() => setZoneHits([]));
      return () => controller.abort();
    }, 250);
    return () => window.clearTimeout(handle);
  }, [locale, query, state.activity]);

  const seasonScope = useMemo(() => {
    if (!seasonHit || state.activity !== "hunting") {
      return { type: "FeatureCollection" as const, features: [] };
    }
    return seasonScopeCollection(
      municipalities,
      seasonHit.overall_state,
      seasonHit.conditions,
    );
  }, [municipalities, seasonHit, state.activity]);

  const displayFeatures = useMemo(
    () =>
      features.map((feature) => ({
        ...feature,
        properties: {
          ...feature.properties,
          legal_state: displayLegalState(
            feature.properties.zone_type,
            state.activity,
            feature.properties.legal_state ?? "unknown",
          ) as typeof feature.properties.legal_state,
        },
      })),
    [features, state.activity],
  );

  const counts = useMemo(() => {
    const tally: Record<string, number> = {};
    for (const feature of displayFeatures) {
      const layer = layerForZoneType(feature.properties.zone_type);
      if (!layer) continue;
      tally[layer] = (tally[layer] ?? 0) + 1;
    }
    return tally;
  }, [displayFeatures]);

  const collection = useMemo<GeoJSON.FeatureCollection>(
    () => ({
      type: "FeatureCollection",
      features: displayFeatures.flatMap((feature) => {
        if (!feature.geometry) return [];
        if (
          state.states.length > 0 &&
          feature.properties.legal_state &&
          !state.states.includes(feature.properties.legal_state)
        ) {
          return [];
        }
        return [
          {
            type: "Feature" as const,
            id: feature.id,
            properties: feature.properties,
            geometry: feature.geometry,
          },
        ];
      }),
    }),
    [displayFeatures, state.states],
  );

  const hiddenLayers = LAYER_IDS.filter(
    (layer) => !state.layers.includes(layer),
  );
  const shareTarget = buildShareUrl(
    typeof window === "undefined" ? "" : window.location.origin,
    pathname,
    state,
    shareOpen,
  );
  const showMap = state.view !== "list" && config.styleUrl && !mapFailed;
  const showList = state.view !== "map" || mapFailed || !config.styleUrl;

  async function copyLink(includePin: boolean) {
    const url = buildShareUrl(
      window.location.origin,
      pathname,
      state,
      includePin,
    );
    trackMapEvent(MAP_EVENTS.share_link_created, {
      includes_point: includePin,
    });
    try {
      if (navigator.share && includePin) {
        await navigator.share({ url });
        setShareOpen(false);
        return;
      }
      await navigator.clipboard.writeText(url);
      setShareFailed(false);
      setShareOpen(false);
    } catch {
      setShareFailed(true);
    }
  }

  return (
    <div className="relative h-[calc(100dvh-3.5rem)] sm:h-[calc(100dvh-4rem)] lg:h-[calc(100dvh-4.25rem)]">
      <div className="pointer-events-none absolute inset-x-3 top-3 z-30 flex flex-wrap items-center gap-2 lg:right-[23.5rem]">
        <div className="pointer-events-auto flex gap-1 rounded-full border border-border bg-card/95 p-1 shadow-[var(--shadow-raised)]">
          <Button
            type="button"
            size="sm"
            variant={state.activity === "hunting" ? "primary" : "ghost"}
            onClick={() => update({ activity: "hunting" }, "push")}
          >
            {copy.hunting}
          </Button>
          <Button
            type="button"
            size="sm"
            variant={state.activity === "fishing" ? "primary" : "ghost"}
            onClick={() => update({ activity: "fishing" }, "push")}
          >
            {copy.fishing}
          </Button>
        </div>
        <div className="pointer-events-auto w-56 sm:w-64">
          <Input
            aria-label={copy.search}
            placeholder={copy.search}
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            className="h-8 bg-card/95 px-3 text-sm"
          />
        </div>
        <Button
          type="button"
          size="sm"
          className="pointer-events-auto"
          onClick={useMyLocation}
        >
          {copy.useLocation}
        </Button>
      </div>
      {hover ? (
        <div
          role="tooltip"
          className="pointer-events-none absolute z-30 rounded-[var(--radius-md)] border border-border bg-card px-2 py-1 text-xs shadow-[var(--shadow-raised)]"
          style={{ left: hover.x + 12, top: hover.y + 56 }}
        >
          {hover.name}
        </div>
      ) : null}
      <div
        className={
          showList && state.view === "split"
            ? "grid h-full lg:grid-cols-[minmax(0,1fr)_22rem]"
            : "h-full"
        }
      >
        {showMap ? (
          <MapCanvas
            config={{
              ...config,
              center: [state.lng, state.lat],
              zoom: state.z,
            }}
            data={collection}
            selectedId={state.zone}
            hoverId={hover?.id ?? null}
            point={
              state.pin
                ? { longitude: state.pin.lng, latitude: state.pin.lat }
                : null
            }
            user={
              userFix
                ? {
                    longitude: userFix.longitude,
                    latitude: userFix.latitude,
                    radius: accuracyRing(
                      userFix.longitude,
                      userFix.latitude,
                      userFix.accuracy,
                    ),
                  }
                : null
            }
            boundary={
              evaluation?.boundary_warning && state.pin
                ? accuracyRing(state.pin.lng, state.pin.lat, 120)
                : null
            }
            hiddenLayers={hiddenLayers}
            seasonScope={seasonScope}
            onReady={(map) => {
              mapRef.current = map;
              const bounds = map.getBounds();
              void loadViewport({
                west: bounds.getWest(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                north: bounds.getNorth(),
                zoom: map.getZoom(),
              });
            }}
            onMoveEnd={onMoveEnd}
            onClick={onMapClick}
            onHover={setHover}
            onError={() => setMapFailed(true)}
          />
        ) : (
          <div
            className="flex h-full items-center justify-center p-6 text-sm"
            role="status"
          >
            {mapFailed ? copy.mapFailed : copy.basemapMissing}
          </div>
        )}
        <aside
          className={
            state.view === "list"
              ? "h-full overflow-auto bg-card p-4"
              : "pointer-events-none absolute inset-x-0 bottom-0 z-20 max-h-[68%] p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:top-3 lg:right-3 lg:bottom-3 lg:left-auto lg:h-auto lg:max-h-none lg:w-[22rem] lg:p-0"
          }
        >
          <div
            className="pointer-events-auto h-full max-h-full overflow-auto rounded-[var(--radius-xl)] border border-border bg-card/95 p-4 shadow-[var(--shadow-overlay)]"
            style={{
              maxHeight:
                snap === "collapsed"
                  ? "5.5rem"
                  : snap === "medium"
                    ? "min(42dvh, 100%)"
                    : "100%",
            }}
          >
            <div className="sticky top-0 z-10 -mx-4 mb-3 space-y-2 border-b border-border bg-card/95 px-4 pt-0 pb-3">
              <h1 className="text-base font-semibold leading-tight">
                {copy.title}
              </h1>
              <div className="flex gap-1" role="group" aria-label={copy.map}>
                {(["map", "list", "split"] as MapViewMode[]).map((mode) => (
                  <Button
                    key={mode}
                    type="button"
                    size="sm"
                    variant={state.view === mode ? "primary" : "ghost"}
                    onClick={() => {
                      update({ view: mode }, "push");
                      trackMapEvent(MAP_EVENTS.view_mode_changed, {
                        view: mode,
                      });
                    }}
                  >
                    {copy[mode]}
                  </Button>
                ))}
              </div>
            </div>
            <p className="mb-3 line-clamp-2 text-sm text-muted-foreground">
              {copy.summary}
            </p>
            {ignoredLink ? (
              <p className="mb-2 text-sm">{copy.invalidLink}</p>
            ) : null}
            {offline ? (
              <p role="status" className="mb-2 text-sm font-medium">
                {copy.offline}
              </p>
            ) : null}
            {overlay === "hidden" ? (
              <p role="status" className="mb-2 text-sm font-medium">
                {copy.overlayHidden}
              </p>
            ) : null}
            {overlay === "error" ? (
              <p role="alert" className="mb-2 text-sm font-medium">
                {copy.overlayError} {stale ? copy.stale : ""}
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="ml-2"
                  onClick={() => mapRef.current?.fire("moveend")}
                >
                  {copy.retry}
                </Button>
              </p>
            ) : null}
            {overlay === "zoom" ? (
              <p className="mb-2 text-sm">{copy.zoomCloser}</p>
            ) : null}
            {overlay === "empty" ? (
              <p className="mb-2 text-sm">{copy.overlayEmpty}</p>
            ) : null}
            {overlay === "incomplete" ? (
              <p className="mb-2 text-sm">{copy.overlayIncomplete}</p>
            ) : null}
            {generatedAt ? (
              <p className="mb-2 text-xs text-muted-foreground">
                {copy.freshness} {generatedAt}
              </p>
            ) : null}
            <div className="mb-3 flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant={state.activity === "hunting" ? "primary" : "outline"}
                onClick={() => update({ activity: "hunting" }, "push")}
              >
                {copy.hunting}
              </Button>
              <Button
                type="button"
                size="sm"
                variant={state.activity === "fishing" ? "primary" : "outline"}
                onClick={() => update({ activity: "fishing" }, "push")}
              >
                {copy.fishing}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setFiltersOpen((open) => !open)}
              >
                {copy.filters}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => {
                  const today = tbilisiToday();
                  update(
                    {
                      mode: "date",
                      date: today,
                      from: today,
                      to: today,
                      species: null,
                      states: [],
                      layers: ["protected", "hunting", "special", "wildlife"],
                    },
                    "push",
                  );
                  setFiltersOpen(false);
                }}
              >
                {copy.resetFilters}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() =>
                  setSnap(snap === "expanded" ? "collapsed" : "expanded")
                }
              >
                {snap === "expanded" ? copy.close : copy.results}
              </Button>
            </div>
            {seasonHit ? (
              <div className="mb-3 space-y-2 rounded-xl border border-border bg-muted/40 p-3 text-sm">
                <p className="font-medium">
                  {seasonHit.species.common_name ??
                    seasonHit.species.scientific_name}
                  {" · "}
                  {copy.outcomes[seasonHit.overall_state] ??
                    seasonHit.overall_state}
                </p>
                {seasonHit.overall_state === "conflict"
                  ? seasonHit.period_statements?.map((statement) => (
                      <p key={statement.source}>{statement.text}</p>
                    ))
                  : null}
                {seasonHit.overall_state !== "conflict" &&
                seasonHit.conditions.some(
                  (condition) => condition.operator !== "not_equals",
                ) ? (
                  <p>
                    {copy.onlyIn}:{" "}
                    {seasonHit.conditions
                      .filter(
                        (condition) => condition.operator !== "not_equals",
                      )
                      .map((condition) => condition.string_value)
                      .filter(Boolean)
                      .join(", ")}
                  </p>
                ) : null}
                {seasonHit.overall_state !== "conflict" &&
                seasonHit.conditions.some(
                  (condition) => condition.operator === "not_equals",
                ) ? (
                  <p>
                    {copy.excluding}:{" "}
                    {seasonHit.conditions
                      .filter(
                        (condition) => condition.operator === "not_equals",
                      )
                      .map((condition) => condition.string_value)
                      .filter(Boolean)
                      .join(", ")}
                  </p>
                ) : null}
                <p className="text-muted-foreground">
                  {seasonHit.overall_state === "conflict"
                    ? copy.explain.conflict
                    : copy.seasonScopeMissing}
                </p>
              </div>
            ) : null}
            {filtersOpen ? (
              <div className="mb-4 space-y-3">
                <div className="space-y-1.5">
                  <p className="text-sm font-medium">{copy.modeLabel}</p>
                  <Select
                    value={state.mode}
                    onValueChange={(value) =>
                      update(
                        { mode: value as ShareableMapState["mode"] },
                        "push",
                      )
                    }
                  >
                    <SelectTrigger aria-label={copy.modeLabel}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="date">{copy.modes.date}</SelectItem>
                      <SelectItem value="range">{copy.modes.range}</SelectItem>
                      <SelectItem value="any">{copy.modes.any}</SelectItem>
                      <SelectItem value="timeline">
                        {copy.modes.timeline}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <p className="text-xs text-muted-foreground">
                  {copy.legalTime}
                </p>
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                      update({ mode: "date", date: tbilisiToday() }, "push")
                    }
                  >
                    {copy.presets.today}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                      update(
                        { mode: "range", ...weekRange(tbilisiToday()) },
                        "push",
                      )
                    }
                  >
                    {copy.presets.week}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                      update(
                        {
                          mode: "range",
                          from: tbilisiToday(),
                          to: addCalendarDays(tbilisiToday(), 6),
                        },
                        "push",
                      )
                    }
                  >
                    {copy.presets.next7}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                      update(
                        { mode: "range", ...monthRange(tbilisiToday()) },
                        "push",
                      )
                    }
                  >
                    {copy.presets.month}
                  </Button>
                </div>
                {state.mode === "date" ? (
                  <label className="block text-sm">
                    {copy.date}
                    <Input
                      type="date"
                      value={state.date}
                      onChange={(event) =>
                        update({ date: event.target.value }, "push")
                      }
                    />
                  </label>
                ) : null}
                {state.mode === "range" || state.mode === "timeline" ? (
                  <div className="grid grid-cols-2 gap-2">
                    <label className="text-sm">
                      {copy.from}
                      <Input
                        type="date"
                        value={state.from}
                        onChange={(event) =>
                          update({ from: event.target.value }, "push")
                        }
                      />
                    </label>
                    <label className="text-sm">
                      {copy.to}
                      <Input
                        type="date"
                        value={state.to}
                        onChange={(event) =>
                          update({ to: event.target.value }, "push")
                        }
                      />
                    </label>
                  </div>
                ) : null}
                {state.mode === "any" ? (
                  <p className="text-sm">{copy.seasonSkipped}</p>
                ) : null}
                <div className="space-y-1.5">
                  <p className="text-sm font-medium">
                    {state.activity === "hunting"
                      ? copy.huntingObject
                      : copy.species}
                  </p>
                  <Select
                    value={state.species ?? "none"}
                    onValueChange={(value) => {
                      update(
                        { species: value === "none" ? null : value },
                        "push",
                      );
                      if (value !== "none") setSnap("expanded");
                      trackMapEvent(MAP_EVENTS.filter_applied, {
                        filter: "species",
                      });
                    }}
                  >
                    <SelectTrigger
                      aria-label={
                        state.activity === "hunting"
                          ? copy.huntingObject
                          : copy.species
                      }
                    >
                      <SelectValue placeholder={copy.speciesChoose} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">{copy.speciesChoose}</SelectItem>
                      {speciesCatalog.map((species) => (
                        <SelectItem key={species.id} value={species.slug}>
                          {species.common_name ?? species.scientific_name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                {zoneHits.length > 0 ? (
                  <ul aria-label={copy.search} className="space-y-1">
                    {zoneHits.map((hit) => (
                      <li key={hit.id}>
                        <button
                          type="button"
                          className="min-h-11 w-full text-left text-sm"
                          onClick={() => {
                            update({ zone: hit.id }, "push");
                            flyToZone(mapRef.current, hit.bbox);
                          }}
                        >
                          {hit.name}
                        </button>
                      </li>
                    ))}
                  </ul>
                ) : null}
                <p className="text-xs text-muted-foreground">
                  {copy.searchHint}
                </p>
                <p className="text-xs text-muted-foreground">
                  {copy.addressUnavailable}
                </p>
                <LayerControl
                  copy={copy}
                  layers={state.layers}
                  counts={counts}
                  onChange={(layers) => {
                    update({ layers }, "push");
                    trackMapEvent(MAP_EVENTS.filter_applied, {
                      filter: "layers",
                    });
                  }}
                />
                <div className="grid grid-cols-2 gap-2">
                  <label className="text-sm">
                    {copy.longitude}
                    <Input
                      inputMode="decimal"
                      value={manualLng}
                      onChange={(event) => setManualLng(event.target.value)}
                    />
                  </label>
                  <label className="text-sm">
                    {copy.latitude}
                    <Input
                      inputMode="decimal"
                      value={manualLat}
                      onChange={(event) => setManualLat(event.target.value)}
                    />
                  </label>
                </div>
                <Button
                  type="button"
                  size="sm"
                  onClick={() => {
                    const longitude = Number(manualLng);
                    const latitude = Number(manualLat);
                    if (
                      !Number.isFinite(longitude) ||
                      !Number.isFinite(latitude)
                    )
                      return;
                    void evaluatePoint(longitude, latitude, null);
                  }}
                >
                  {copy.checkCoordinates}
                </Button>
              </div>
            ) : null}
            <div className="mb-3 flex flex-wrap gap-2">
              <Button type="button" size="sm" onClick={useMyLocation}>
                {copy.useLocation}
              </Button>
              {state.pin ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    void evaluatePoint(
                      state.pin!.lng,
                      state.pin!.lat,
                      userFix?.accuracy ?? null,
                    )
                  }
                >
                  {copy.checkLocation}
                </Button>
              ) : null}
            </div>
            {geoAsked ? (
              <p className="mb-2 text-xs text-muted-foreground">
                {copy.locationWhy}
              </p>
            ) : null}
            {geoMessage ? (
              <p role="status" className="mb-2 text-sm">
                {geoMessage}
              </p>
            ) : null}
            {state.pin ? (
              <p className="mb-2 text-xs">
                {copy.longitude} {state.pin.lng.toFixed(4)}, {copy.latitude}{" "}
                {state.pin.lat.toFixed(4)}
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    void navigator.clipboard.writeText(
                      `${state.pin!.lng.toFixed(4)}, ${state.pin!.lat.toFixed(4)}`,
                    );
                    setCopied(true);
                  }}
                >
                  {copied ? copy.copied : copy.copyCoordinates}
                </Button>
              </p>
            ) : null}
            {overlaps.length > 1 ? (
              <ul aria-label={copy.zonesAtPoint} className="mb-3 space-y-1">
                {overlaps.map((id) => {
                  const feature = features.find(
                    (item) => item.properties.id === id,
                  );
                  return (
                    <li key={id}>
                      <button
                        type="button"
                        className="min-h-11 text-sm underline"
                        onClick={() => update({ zone: id }, "push")}
                      >
                        {feature?.properties.name ?? id}
                      </button>
                    </li>
                  );
                })}
              </ul>
            ) : null}
            <LocationResult
              copy={copy}
              evaluation={evaluation}
              loading={evaluating}
              accuracyMeters={userFix?.accuracy ?? null}
            />
            {evaluation ? (
              <RecommendationSection
                request={{
                  placement: "map_location_result",
                  locale,
                  contextToken: evaluation.context_token ?? null,
                  activity:
                    state.activity === "fishing" ? "fishing" : "hunting",
                }}
              />
            ) : null}
            {state.zone ? (
              <div className="mt-4">
                <ZoneDetail
                  copy={copy}
                  zone={zone?.id === state.zone ? zone : null}
                  onZoom={() => {
                    const feature = features.find(
                      (item) => item.properties.id === state.zone,
                    );
                    flyToZone(mapRef.current, feature?.bbox);
                  }}
                  onShare={() => void copyLink(false)}
                />
              </div>
            ) : null}
            <div className="mt-4">
              <ResultsList
                copy={copy}
                features={displayFeatures}
                selectedId={state.zone}
                onSelect={(id) => {
                  update({ zone: id }, "push");
                  const feature = features.find(
                    (item) => item.properties.id === id,
                  );
                  flyToZone(mapRef.current, feature?.bbox);
                  trackMapEvent(MAP_EVENTS.zone_opened, { source: "list" });
                }}
              />
            </div>
            <div className="mt-4">
              <MapLegend copy={copy} collapsed={!legendOpen} />
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => setLegendOpen((open) => !open)}
              >
                {copy.legend}
              </Button>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => void copyLink(false)}
              >
                {copy.share}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setShareOpen(true)}
                disabled={!state.pin}
              >
                {copy.sharePoint}
              </Button>
            </div>
            <ShareDialog
              copy={copy}
              open={shareOpen}
              url={shareTarget}
              failed={shareFailed}
              onCancel={() => setShareOpen(false)}
              onConfirm={() => void copyLink(true)}
            />
            <p className="mt-4 text-xs text-muted-foreground">
              {copy.disclaimer}
            </p>
            {config.styleMode === "development-fallback" ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Basemap: OpenFreeMap / © OpenStreetMap contributors
              </p>
            ) : null}
            <p className="mt-1 text-xs text-muted-foreground">
              {features.length} {copy.overlaysInView}
            </p>
          </div>
        </aside>
      </div>
    </div>
  );
}

const LAYER_IDS: LayerId[] = [
  "protected",
  "hunting",
  "fishing",
  "special",
  "wildlife",
  "admin",
];
