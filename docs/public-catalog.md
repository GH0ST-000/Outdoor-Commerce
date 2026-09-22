# Public Catalog API (Day 12)

The public catalog is a read-only storefront contract. It composes Catalog, Media, Pricing, and Inventory through application query services and rebuildable projections. Controllers stay thin: they resolve locale/currency, validate filters, invoke a query, and return a cached resource.

Meilisearch is out of scope. `q` is a bounded MySQL prefix/contains search. Day 16 can replace the search implementation without changing the URL contract.

## Endpoints

All routes are unauthenticated `GET` under `/api/v1/catalog`.

| Method | Path | Rate limiter |
| --- | --- | --- |
| GET | `/categories` | `catalog.public` (120/min/IP) |
| GET | `/categories/{slug}` | `catalog.public` |
| GET | `/brands` | `catalog.public` |
| GET | `/brands/{slug}` | `catalog.public` |
| GET | `/products` | `catalog.public.list` (60/min/IP). Search (`q`) uses a separate 20/min/IP bucket |
| GET | `/products/facets` | `catalog.public.facets` (30/min) |
| GET | `/products/{slug}` | `catalog.public` |

Default pagination is **10**. Maximum `per_page` is **48**. Default currency is **GEL**. Price inputs and outputs use **integer minor units** (tetri). `locale` may be supplied as `?locale=`, `X-Locale`, or `Accept-Language`. Unsupported locales return `CATALOG_LOCALE_UNSUPPORTED`.

## Public eligibility

A product is public only when all of the following hold (`PublicProductEligibility`):

- Status is active and the row is not deleted
- A valid selected-locale or Georgian fallback translation and slug exist
- Primary category exists, is active, and every ancestor is active
- Assigned brand, when present, is active
- At least one publicly eligible variant exists
- Ready product or variant image exists (`catalog.public.require_ready_media`, enabled)

A public variant must be active, combination-complete for its axes, SKU-valid, and priced on the default GEL price list (`retail_gel`). **Unpriced variants are excluded** from storefront selection. They are never shown as zero-priced. Out-of-stock variants remain visible; `purchasable` is true only when `available_to_sell > 0` and a price exists.

Non-public detail requests return **404** (`CATALOG_PRODUCT_NOT_FOUND`). Draft existence is not revealed.

## Availability

Inventory is the authority. Public responses expose `in_stock | low_stock | out_of_stock | unavailable` plus `purchasable` and `low_stock`. Exact on-hand, reserved, safety stock, warehouse IDs, and addresses are never returned.

## Pricing

Pricing is the authority. List cards use projected minimum/maximum **final** GEL amounts. Product detail re-quotes live prices and promotions so purchase-critical figures are not stale. Missing price is `null`, never `0`. Currencies are not mixed.

## Locale and slugs

Primary locale `ka`, fallback `ka`, secondary `en`. Georgian Unicode slugs are valid. Missing English content falls back to Georgian and sets `used_fallback`. Slug lookup accepts the requested locale and the fallback locale. `Content-Language` and `Vary: Accept-Language, Accept-Encoding, X-Locale` are always set.

## Category browsing

`category=hunting` includes the category **and active descendants** by default (`include_descendants=1`). Inactive descendants are omitted. Pass `include_descendants=0` for direct assignments only.

## Attribute filters

Stable codes only. Multiple values in one attribute are **OR**. Different attributes are **AND**. A product matches only when **one** publicly eligible variant satisfies the full combination.

## Sorting

Whitelist: `default`, `featured`, `newest`, `price_asc`, `price_desc`, `name_asc`, `name_desc`. Price sorts use minimum final projected price with an id tie-breaker.

## Projections

Tables `public_catalog_variant_projections` and `public_catalog_product_projections` are disposable read models. Rebuild:

```bash
php artisan catalog:rebuild-public-projections
php artisan catalog:rebuild-public-projections --product=12
php artisan catalog:verify-public-projections
php artisan catalog:refresh-time-sensitive-projections
```

The scheduler runs the time-sensitive command **every minute**. Production cron must execute `php artisan schedule:run` every minute so scheduled prices/promotions cannot hide behind cache TTL.

Projection lag is typically seconds (queue `catalog`) plus up to one minute for time-only boundaries.

## Cache

Redis (or the configured cache store) keys include API version, locale, currency, resource, normalized params, and catalog/pricing/inventory cache versions. Equivalent query parameter order hashes the same. TTL is `min(60s, next price/promotion boundary)`. Stampede locks wrap expensive rebuilds. Failed transactions do not bump versions (`DB::afterCommit`). Empty Redis is a miss, not an error.

HTTP: `ETag`, `If-None-Match` → `304` with empty body, `Cache-Control: public, max-age=…, stale-while-revalidate=300`.

## Frontend

Typed client: `frontend/src/features/catalog/api/public-catalog-client.ts`. Adapters map API cards/details onto existing storefront presentation types. Homepage merchandising, category landings, and product listings consume the same public resources (Day 14). Product detail (Day 15) uses the product resource’s variant matrix without a second price or availability request. See [storefront.md](storefront.md), [product-detail.md](product-detail.md), and [ADR 0009](adr/0009-url-driven-storefront-filters.md).

## Day 16

Catalog search uses Meilisearch as a derived variant-aware index. The `q` parameter still uses the same public URL contract. Final cards are hydrated from Public Catalog projections. See [search.md](search.md) and [ADR 0011](adr/0011-meilisearch-derived-search.md).
