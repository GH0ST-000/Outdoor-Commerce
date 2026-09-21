# ADR 0007: Rebuildable public catalog projections for cross-domain storefront reads

## Status

Accepted — Day 12

## Context

The storefront must compose Catalog, Media, Pricing, and Inventory for every product card. Querying each domain per product creates N+1 work, inconsistent cache TTLs, and a tempting place to leak warehouse quantities or draft prices. Scheduled prices and promotions can start or end without a row write, so a long Redis TTL is not sufficient.

## Decision

1. **Public catalog projections** (`public_catalog_variant_projections`, `public_catalog_product_projections`) are rebuildable read models. They never replace VariantPrice, InventoryBalance, or Product as sources of truth and never write back to those tables.

2. **List/facet endpoints** read projections for eligibility, final GEL price, on-sale, and aggregated in-stock flags, then hydrate localized names and ready media in bounded batches.

3. **Product detail** re-quotes pricing and availability from the Pricing and Inventory contracts so a shopper does not see a stale purchasable state. Projections still help choose the default variant and price range.

4. **Redis** caches complete public GET envelopes. Cache keys include catalog, pricing, and inventory versions. Redis is a cache, not a catalog. The API is correct with an empty store.

5. **Time-sensitive refresh** (`catalog:refresh-time-sensitive-projections`, every minute) detects price/promotion window boundaries that occurred without a write and refreshes only affected variants.

6. **Public inventory** exposes status + purchasable only. Exact reserved/on-hand figures stay inside Inventory so a future cart can still create a reservation against current balances.

7. **Basic MySQL `q`** is an explicit stopgap. Day 16 Meilisearch can replace search and facets without changing the public URL contract.

## Consequences

- Operators must run the Laravel scheduler and a `catalog` queue worker.
- After large imports, run `catalog:rebuild-public-projections` then `catalog:verify-public-projections`.
- Projection lag is observable; verification is read-only by default.
- Homepage merchandising (Day 14) consumes the same public resources.

## Alternatives considered

- **Per-request joins across all domains**: rejected — unbounded query growth and no stable sort index for final promotional price.
- **Redis as source of truth**: rejected — cannot survive flush, and scheduled boundaries would still require a clock-aware rebuild.
- **Exposing exact stock**: rejected — leaks warehouse policy and races the reservation model.
