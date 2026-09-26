"use client";

import { useEffect, useRef } from "react";
import type { Map as MapLibreMap, GeoJSONSource } from "maplibre-gl";
import { categoryLayerSpecs, LEGAL_SOURCE_ID } from "@/features/map/lib/layers";
import {
  mapPalette,
  paintForLegalState,
  protectedCategoryPaint,
} from "@/features/map/lib/cartography";
import {
  styleUrlWithToken,
  type MapConfig,
} from "@/features/map/lib/map-config";
import { featureCollectionBbox } from "@/features/map/lib/season-scope";
import "maplibre-gl/dist/maplibre-gl.css";

export type MapFeatureClick = {
  ids: string[];
  longitude: number;
  latitude: number;
};

type Props = {
  config: MapConfig;
  data: GeoJSON.FeatureCollection;
  selectedId: string | null;
  hoverId: string | null;
  point: { longitude: number; latitude: number } | null;
  user: {
    longitude: number;
    latitude: number;
    radius: GeoJSON.Polygon | null;
  } | null;
  boundary: GeoJSON.Polygon | null;
  hiddenLayers: string[];
  seasonScope?: GeoJSON.FeatureCollection;
  onReady?: (map: MapLibreMap) => void;
  onMoveEnd?: (view: {
    west: number;
    south: number;
    east: number;
    north: number;
    zoom: number;
    lng: number;
    lat: number;
  }) => void;
  onClick?: (event: MapFeatureClick) => void;
  onHover?: (
    hover: { id: string; name: string; x: number; y: number } | null,
  ) => void;
  onError?: () => void;
};

const EMPTY: GeoJSON.FeatureCollection = {
  type: "FeatureCollection",
  features: [],
};

function pointCollection(
  point: { longitude: number; latitude: number } | null,
): GeoJSON.FeatureCollection {
  if (!point) return EMPTY;
  return {
    type: "FeatureCollection",
    features: [
      {
        type: "Feature",
        properties: {},
        geometry: {
          type: "Point",
          coordinates: [point.longitude, point.latitude],
        },
      },
    ],
  };
}

function polygonCollection(
  polygon: GeoJSON.Polygon | null,
): GeoJSON.FeatureCollection {
  if (!polygon) return EMPTY;
  return {
    type: "FeatureCollection",
    features: [{ type: "Feature", properties: {}, geometry: polygon }],
  };
}

export function MapCanvas(props: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<MapLibreMap | null>(null);
  const fittedScope = useRef("");
  const propsRef = useRef(props);
  useEffect(() => {
    propsRef.current = props;
  });

  useEffect(() => {
    const node = containerRef.current;
    if (!node || !props.config.styleUrl) return;
    let disposed = false;
    let map: MapLibreMap | null = null;

    void import("maplibre-gl")
      .then((maplibre) => {
        if (disposed || !containerRef.current) return;
        // Next does not emit the worker's sibling module, so both files are served from public/.
        maplibre.setWorkerUrl("/maplibre/maplibre-gl-worker.mjs");
        const reduced =
          typeof window.matchMedia === "function" &&
          window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        map = new maplibre.Map({
          container: containerRef.current,
          style: styleUrlWithToken(props.config.styleUrl!, props.config.token),
          center: props.config.center,
          zoom: props.config.zoom,
          minZoom: props.config.minZoom,
          maxZoom: props.config.maxZoom,
          maxBounds: props.config.bounds,
          attributionControl: { compact: true },
          fadeDuration: reduced ? 0 : 300,
          refreshExpiredTiles: false,
        });
        map.addControl(
          new maplibre.NavigationControl({ showCompass: false }),
          "bottom-right",
        );
        mapRef.current = map;
        const ensure = () => {
          if (!map || !map.isStyleLoaded()) return;
          installLayers(map);
          sync(map, propsRef.current, fittedScope);
        };
        const ready = () => {
          ensure();
          try {
            propsRef.current.onReady?.(map!);
          } catch {
            // The camera is not readable until the style has settled.
          }
        };
        map.on("load", ready);
        map.on("style.load", ready);
        map.on("error", (event) => {
          const message =
            event.error instanceof Error ? event.error.message : "";
          const details = event as typeof event & {
            sourceId?: unknown;
            tile?: unknown;
          };
          if (
            details.sourceId ||
            details.tile ||
            /tile|ajax|failed to fetch|network/i.test(message)
          )
            return;
          if (/does not exist in the map's style/i.test(message)) return;
          propsRef.current.onError?.();
        });
        map.on("moveend", () => {
          if (!map) return;
          const bounds = map.getBounds();
          const center = map.getCenter();
          propsRef.current.onMoveEnd?.({
            west: bounds.getWest(),
            south: bounds.getSouth(),
            east: bounds.getEast(),
            north: bounds.getNorth(),
            zoom: map.getZoom(),
            lng: center.lng,
            lat: center.lat,
          });
        });
        map.on("mousemove", (event) => {
          if (!map) return;
          const hits = renderedFills(map, event.point);
          const id = hits[0]?.properties?.id;
          const name = hits[0]?.properties?.name;
          propsRef.current.onHover?.(
            typeof id === "string"
              ? {
                  id,
                  name: typeof name === "string" ? name : id,
                  x: event.point.x,
                  y: event.point.y,
                }
              : null,
          );
        });
        map.on("click", (event) => {
          if (!map) return;
          const hits = renderedFills(map, event.point);
          const ids = [
            ...new Set(
              hits
                .map((feature) => feature.properties?.id)
                .filter((id): id is string => typeof id === "string"),
            ),
          ];
          propsRef.current.onClick?.({
            ids,
            longitude: event.lngLat.lng,
            latitude: event.lngLat.lat,
          });
        });
      })
      .catch(() => propsRef.current.onError?.());

    const observer = new ResizeObserver(() => map?.resize());
    observer.observe(node);

    return () => {
      disposed = true;
      observer.disconnect();
      map?.remove();
      mapRef.current = null;
    };
    // The map instance is created once per mount. Later prop updates go through the sync effect.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.config.styleUrl]);

  const data = props.data;
  const seasonScope = props.seasonScope;
  const point = props.point;
  const user = props.user;
  const boundary = props.boundary;
  const hoverId = props.hoverId;
  const selectedId = props.selectedId;
  const hiddenLayers = props.hiddenLayers;

  useEffect(() => {
    const map = mapRef.current;
    if (!map || !map.isStyleLoaded()) return;
    sync(
      map,
      {
        data,
        seasonScope,
        point,
        user,
        boundary,
        hoverId,
        selectedId,
        hiddenLayers,
      },
      fittedScope,
    );
  }, [
    data,
    seasonScope,
    point,
    user,
    boundary,
    hoverId,
    selectedId,
    hiddenLayers,
  ]);

  return (
    <div
      ref={containerRef}
      className="map-legal h-full w-full"
      data-testid="legal-map-canvas"
    />
  );
}

function renderedFills(map: MapLibreMap, point: { x: number; y: number }) {
  const layers = categoryLayerSpecs()
    .filter((spec) => spec.kind === "fill")
    .map((spec) => spec.id)
    .filter((id) => Boolean(map.getLayer(id)));
  if (layers.length === 0) return [];
  try {
    return map.queryRenderedFeatures([point.x, point.y], { layers });
  } catch {
    return [];
  }
}

function setSource(
  map: MapLibreMap,
  id: string,
  data: GeoJSON.FeatureCollection,
) {
  const source = map.getSource(id) as GeoJSONSource | undefined;
  if (source) {
    source.setData(data);
    return;
  }
  map.addSource(id, { type: "geojson", data });
}

function installLayers(map: MapLibreMap) {
  if (map.getSource(LEGAL_SOURCE_ID)) return;
  const before = map
    .getStyle()
    .layers?.find((layer) => layer.type === "symbol")?.id;
  setSource(map, LEGAL_SOURCE_ID, EMPTY);
  setSource(map, "season-scope", EMPTY);
  setSource(map, "selected-point", EMPTY);
  setSource(map, "user-location", EMPTY);
  setSource(map, "user-accuracy", EMPTY);
  setSource(map, "boundary-warning", EMPTY);
  const categoryMatch = (part: "fill" | "line"): unknown[] => [
    "match",
    ["get", "zone_type"],
    ...Object.entries(protectedCategoryPaint).flatMap(([zoneType, paint]) => [
      zoneType,
      paint[part],
    ]),
    mapPalette.unknown[part],
  ];
  const legalMatch = (part: "fill" | "line"): unknown[] => [
    "match",
    ["get", "legal_state"],
    "prohibited",
    paintForLegalState("prohibited")[part],
    "conditional",
    paintForLegalState("conditional")[part],
    "allowed",
    paintForLegalState("allowed")[part],
    "conflict",
    paintForLegalState("conflict")[part],
    mapPalette.unknown[part],
  ];
  const paintExpression = (part: "fill" | "line"): unknown[] => [
    "case",
    ["==", ["get", "legal_state"], "unknown"],
    categoryMatch(part),
    legalMatch(part),
  ];
  for (const spec of categoryLayerSpecs()) {
    if (spec.kind === "fill") {
      map.addLayer(
        {
          id: spec.id,
          type: "fill",
          source: LEGAL_SOURCE_ID,
          filter: spec.filter as never,
          paint: {
            "fill-color": paintExpression("fill") as never,
            "fill-opacity":
              spec.layer === "admin"
                ? 0.08
                : spec.layer === "protected"
                  ? 0.55
                  : 0.35,
          },
        },
        before,
      );
    } else {
      map.addLayer(
        {
          id: spec.id,
          type: "line",
          source: LEGAL_SOURCE_ID,
          filter: spec.filter as never,
          paint: {
            "line-color":
              spec.layer === "admin"
                ? mapPalette.admin.line
                : (paintExpression("line") as never),
            "line-width":
              spec.layer === "admin"
                ? 1.25
                : spec.layer === "protected"
                  ? 2.5
                  : 1.5,
            "line-dasharray": spec.layer === "admin" ? [4, 2] : [1, 0],
          },
        },
        before,
      );
    }
  }
  const beforeProtected = map.getLayer("zone-fill-protected")
    ? "zone-fill-protected"
    : before;
  const allowed = paintForLegalState("allowed");
  map.addLayer(
    {
      id: "season-scope-fill",
      type: "fill",
      source: "season-scope",
      paint: { "fill-color": allowed.fill, "fill-opacity": 0.42 },
    },
    beforeProtected,
  );
  map.addLayer(
    {
      id: "season-scope-line",
      type: "line",
      source: "season-scope",
      paint: { "line-color": allowed.line, "line-width": 1.25 },
    },
    beforeProtected,
  );
  map.addLayer({
    id: "zone-line-hover",
    type: "line",
    source: LEGAL_SOURCE_ID,
    filter: ["==", ["get", "id"], ""],
    paint: { "line-color": mapPalette.hover.line, "line-width": 2.5 },
  });
  map.addLayer({
    id: "zone-line-selected",
    type: "line",
    source: LEGAL_SOURCE_ID,
    filter: ["==", ["get", "id"], ""],
    paint: { "line-color": mapPalette.selected.line, "line-width": 3.5 },
  });
  map.addLayer({
    id: "boundary-warning",
    type: "line",
    source: "boundary-warning",
    paint: {
      "line-color": mapPalette.conflict.line,
      "line-width": 2,
      "line-dasharray": [1, 1],
    },
  });
  map.addLayer({
    id: "user-accuracy",
    type: "fill",
    source: "user-accuracy",
    paint: { "fill-color": mapPalette.user.fill, "fill-opacity": 0.15 },
  });
  map.addLayer({
    id: "user-location",
    type: "circle",
    source: "user-location",
    paint: {
      "circle-radius": 7,
      "circle-color": mapPalette.user.fill,
      "circle-stroke-color": mapPalette.user.line,
      "circle-stroke-width": 2,
    },
  });
  map.addLayer({
    id: "selected-point",
    type: "circle",
    source: "selected-point",
    paint: {
      "circle-radius": 6,
      "circle-color": mapPalette.selected.fill,
      "circle-stroke-color": "#1a1613",
      "circle-stroke-width": 2,
    },
  });
}

function fitSeasonScope(
  map: MapLibreMap,
  scope: GeoJSON.FeatureCollection | undefined,
) {
  if (!scope || scope.features.length === 0) return;
  const bbox = featureCollectionBbox(scope);
  if (!bbox) return;
  const narrow = window.innerWidth < 1024;
  map.fitBounds(
    [
      [bbox[0], bbox[1]],
      [bbox[2], bbox[3]],
    ],
    {
      padding: narrow
        ? {
            top: 72,
            bottom: Math.round(window.innerHeight * 0.46),
            left: 16,
            right: 16,
          }
        : { top: 48, bottom: 48, left: 48, right: 400 },
      duration: 600,
      maxZoom: 9,
    },
  );
}

function sync(
  map: MapLibreMap,
  props: Pick<
    Props,
    | "data"
    | "seasonScope"
    | "point"
    | "user"
    | "boundary"
    | "hoverId"
    | "selectedId"
    | "hiddenLayers"
  >,
  fittedScope?: { current: string },
) {
  if (!map.getSource(LEGAL_SOURCE_ID)) return;
  setSource(map, LEGAL_SOURCE_ID, props.data);
  setSource(map, "season-scope", props.seasonScope ?? EMPTY);
  if (fittedScope) {
    const key = (props.seasonScope?.features ?? [])
      .map((feature) => String(feature.properties?.id ?? ""))
      .join(",");
    if (key && key !== fittedScope.current) {
      fittedScope.current = key;
      fitSeasonScope(map, props.seasonScope);
    }
  }
  setSource(map, "selected-point", pointCollection(props.point));
  setSource(
    map,
    "user-location",
    pointCollection(
      props.user
        ? { longitude: props.user.longitude, latitude: props.user.latitude }
        : null,
    ),
  );
  setSource(
    map,
    "user-accuracy",
    polygonCollection(props.user?.radius ?? null),
  );
  setSource(map, "boundary-warning", polygonCollection(props.boundary));
  if (map.getLayer("zone-line-hover")) {
    map.setFilter("zone-line-hover", [
      "==",
      ["get", "id"],
      props.hoverId ?? "",
    ]);
  }
  if (map.getLayer("zone-line-selected")) {
    map.setFilter("zone-line-selected", [
      "==",
      ["get", "id"],
      props.selectedId ?? "",
    ]);
  }
  for (const spec of categoryLayerSpecs()) {
    if (!map.getLayer(spec.id)) continue;
    map.setLayoutProperty(
      spec.id,
      "visibility",
      props.hiddenLayers.includes(spec.layer) ? "none" : "visible",
    );
  }
}

export function flyToZone(
  map: MapLibreMap | null,
  bbox: [number, number, number, number] | undefined,
) {
  if (!map || !bbox) return;
  map.fitBounds(
    [
      [bbox[0], bbox[1]],
      [bbox[2], bbox[3]],
    ],
    { padding: 48, duration: 600, maxZoom: 12 },
  );
}
