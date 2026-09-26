# Public legal map (Day 28)

The storefront map at `/map` consumes the Day 27 spatial APIs. Locale stays on the existing language control (`ka` default, `en`). There is no `/ka/map` prefix because the app does not use locale path segments.

Legal evaluation remains on the server. The map does not decide whether hunting or fishing is allowed.

## Library

- `maplibre-gl` 5.6.1, loaded with `next/dynamic` and `ssr: false`.
- One `Map` instance per mount. `map.remove()` runs on unmount. Resize observation calls `resize()`.
- No Mapbox GL, Leaflet, or Google Maps.

## Basemap

| Variable | Purpose |
| --- | --- |
| `NEXT_PUBLIC_MAP_STYLE_URL` | Public style JSON. Required in production. |
| `NEXT_PUBLIC_MAP_TOKEN` | Optional public token. `{token}` in the style URL is replaced. Never a secret. |
| `NEXT_PUBLIC_MAP_CENTER_LNG` / `LAT` | Default center. Defaults to Georgia (43.5, 42.2). |
| `NEXT_PUBLIC_MAP_ZOOM` | Default zoom, `6.5`. |
| `NEXT_PUBLIC_MAP_MIN_ZOOM` / `MAX_ZOOM` | `5` and `16`. |
| `NEXT_PUBLIC_MAP_BOUNDS` | `west,south,east,north`. Default bounds cover Georgia. |

Development and tests use `https://tiles.openfreemap.org/styles/liberty` when the style URL is empty. That style carries OpenFreeMap and OpenStreetMap attribution. It is not a production tile backend. Do not point production at the public OSM raster tile servers.

## Client boundary

`app/(storefront)/map/page.tsx` renders metadata, a summary, and `<noscript>`. `LegalMapExperience` is client-only. Filters, the list, and coordinate entry work without treating the canvas as the only interface.

## Viewport lifecycle

Movement waits for `moveend`, then 350ms. Identical keys are skipped. A newer move aborts the previous `AbortController`. A 304 keeps the current features. A failed overlay keeps the previous geometry only with a stale warning. An empty or failed overlay is not described as unrestricted.

Detail is `region` below zoom 9, `local` from 9, and `full` from 12. The map does not request `country`, because that response omits geometry. Viewports wider than the server span limit ask the user to zoom in.

## Layers

One GeoJSON source, `legal-zones`. Fill and line layers, bottom to top: administrative, wildlife, protected, special, fishing, hunting, then hover, selection, boundary warning, accuracy ring, user point, and selected point. Polygon layers are inserted before the first symbol layer so basemap labels stay readable.

`legal_state` colors are paired with a dash pattern and a text mark. See `features/map/lib/cartography.ts`.

## Privacy

Geolocation uses `getCurrentPosition` only after “Use my location”, with a 10s timeout and `maximumAge: 0`. Coordinates are not written to `localStorage` or analytics. Evaluate and lookup responses send `Cache-Control: private, no-store`. Ordinary share links omit `pin`. “Share this point” explains that the link contains the coordinate, then copies it at four decimal places.

## Evaluation

`GET /api/v1/spatial/evaluate` is authoritative. Outside every published polygon stays `unknown` unless a published assignment explicitly allows the activity. `conflict`, near-boundary, and on-boundary stay visible. Dates are sent as `Asia/Tbilisi` noon. Season evaluation runs when a species slug and a from/to period are present. `species_slug` is resolved server-side. The integer `species` parameter remains for existing tests.

`GET /api/v1/spatial/search` finds published zones only. Address geocoding is the unused `PlaceGeocoder` interface.

## Accessibility

Keyboard users can change activity, dates, layers, species, and coordinates, and can open zones from the list. The list is available in list and split view. Outcomes are text plus a mark, not color alone.

## CSP

`frontend/next.config.ts` sets a Content-Security-Policy that allows `'self'`, the configured style origin, `https://tiles.openfreemap.org`, Unsplash images already used by the storefront, and `blob:` workers. `Permissions-Policy` allows geolocation for the site only. Add a new tile host only by configuring the style URL so its origin is included. Do not add a global wildcard.

## Caching

Viewport responses are public, ETag-validated, and cached for `SPATIAL_CACHE_TTL` seconds. There is no offline tile cache and no service worker. Offline mode says live verification is unavailable.

## Analytics

`trackMapEvent` is a no-op, matching storefront analytics, and drops coordinate fields.

## Tests

```bash
cd frontend
npx vitest run src/features/map/tests
npx tsc --noEmit

cd backend
php artisan test --compact tests/Feature/Geography/SpatialZoneTest.php
```

## Troubleshooting

- Production map area says the basemap is not configured: set `NEXT_PUBLIC_MAP_STYLE_URL`.
- Overlays ask you to zoom in: the viewport is wider than `SPATIAL_MAX_VIEWPORT_SPAN` (8°) or the full-detail span (2°).
- A zone click and the legal panel disagree: trust the panel. It came from the evaluator, not from the drawn polygon.
- Style or tiles blocked: the CSP origin must match the style host. Workers need `blob:`.

## Day 29

The Outdoor Context API should call `SpatialLegalEvaluator` and the viewport query instead of reimplementing point-in-polygon or copying GeoJSON styling rules. Product matching must not treat `unknown` or a hidden overlay as allowed.

## Day 30

Evaluate responses include `context_token`. The gear section under the legal panel sends that token to `POST /api/v1/recommendations/contextual` with placement `map_location_result`. It does not send the selected coordinate. A new token replaces the previous list and marks it stale until the next response. See [recommendations.md](recommendations.md).
