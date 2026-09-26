# ADR 0022 — Interactive legal map

## Status

Accepted (Day 28)

## Context

The public map must show published protected and restricted zones, accept a point or a one-time device location, and explain a hunting or fishing result. Days 25–27 already store versioned geometry and evaluate points on the server.

## Decision

1. Use MapLibre GL JS 5.6.1 directly in a client-only map shell. The repository had no map library. MapLibre is open source, has no Mapbox telemetry, and matches React 19 and Next.js 16 without a second rendering stack.
2. The basemap style URL, optional public token, center, zoom, and country bounds come from `NEXT_PUBLIC_MAP_*`. Development and tests fall back to the OpenFreeMap Liberty style. Production without a style URL shows a list instead of calling the public OpenStreetMap raster servers.
3. Legal status is computed only by `SpatialLegalEvaluator`. The browser may hit-test a drawn feature to open details, then asks the server. A missing overlay is shown as unknown or as a failed overlay, never as permission.
4. Exact GPS coordinates are requested only after an explicit action, used once, kept out of ordinary URLs, analytics, and `localStorage`. A point enters a share URL only after a separate confirmation.
5. Zones load by viewport, zoom-based detail, and abortable requests. National full-resolution geometry is not requested at startup.
6. Map and list modes both exist because a canvas cannot be the only way to read a legal result.
7. Display `legal_state` on a viewport feature is a cartographic hint from published assignments. It is not the point conclusion.

## Consequences

- Production needs a provider style URL whose public token, if any, is restricted by domain. Attribution stays in the map control.
- Content-Security-Policy must allow the style origin, its tiles, and `blob:` workers.
- Day 29 can call the same evaluate and viewport contracts when it builds the Outdoor Context API.
