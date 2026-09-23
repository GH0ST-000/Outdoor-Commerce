# ADR 0011: Meilisearch as a derived variant-aware catalog search model

## Status

Accepted — Day 16

## Context

The public catalog already lists, filters, and paginates published products from MySQL projections. Day 12 used a bounded MySQL `q` as an explicit stopgap. Shoppers need typo-tolerant Georgian and English search, autocomplete, and same-variant attribute filtering without making Meilisearch authoritative for publication, price, or stock.

A product-level flattened attribute document can match impossible combinations (black + size L when those values live on different variants). The storefront must never talk to Meilisearch, and master keys must never reach the browser.

## Decision

1. **MySQL remains the source of truth.** Meilisearch is a derived search/read model. Laravel hydrates every public product card through the existing Public Catalog projection layer (`GetPublicProductsQuery::cardsByOrderedIds`). Stale hits are dropped and a repair job is queued.

2. **Variant-level documents.** One search document per publicly sellable variant. Attribute filter keys (`attr_{code}_{value}`) are locale-independent. Selected filters must all match the same variant document.

3. **Product-level deduplication.** Index `distinctAttribute` is `product_id`. Facet counts for the storefront are computed from unique matching product IDs (bounded) then existing MySQL facet queries, because Meilisearch 1.11 facet distribution is variant-weighted when distinct is enabled.

4. **Locale-specific versioned indexes.** Names follow `{prefix}_{type}_{locale}_{schema}` with a testing prefix isolation rule. Rebuild writes a temporary index, applies settings, imports in chunks, swaps onto the live UID, and keeps the previous index until the next successful rebuild.

5. **Server-only Meilisearch access.** Next.js calls Laravel only (`GET /api/v1/catalog/products?q=`, `GET /api/v1/search`, `GET /api/v1/search/suggestions`). A dedicated `SearchGateway` wraps `meilisearch/meilisearch-php`. Laravel Scout is not used: variant documents are not Eloquent models.

6. **Graceful MySQL fallback.** Short connect/request timeouts. If Meilisearch is disabled or unreachable and `SEARCH_FALLBACK_ENABLED=true`, search uses bounded MySQL matching on name/SKU/brand/category. `meta.fallback_used` is true. Category browsing and product detail never depend on Meilisearch. Application boot does not require a live search engine.

7. **Event-driven sync after commit.** Catalog, inventory, and pricing events enqueue idempotent jobs on the `catalog` queue. Inventory bursts are delayed/uniqued. Jobs pass product IDs, never Eloquent models.

## Consequences

- Operators must run Meilisearch, Redis, and a worker that includes the `catalog` queue, plus `search:configure` / `search:rebuild-catalog` after schema changes.
- Search results can lag MySQL by a short eventual-consistency window; hydration prevents leaking hidden products.
- Hostinger shared hosting often cannot run a persistent Meilisearch process. Production may need a VPS, a managed Meilisearch host, or a separate server. This ADR does not change application architecture for that constraint.

## Alternatives considered

- **Laravel Scout:** rejected as the primary path because Scout maps models 1:1 and would fight variant-level documents, locale indexes, and zero-downtime swaps.
- **Product-level flattened attributes:** rejected — false matches across variants.
- **Browser → Meilisearch with a public search key:** rejected — keys, ranking, and eligibility would leak out of Laravel.
- **Elasticsearch / Algolia / Typesense:** rejected — the stack already standardizes on Meilisearch.
