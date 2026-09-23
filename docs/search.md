# Catalog search (Day 16)

Meilisearch is a **derived** search index. MySQL remains authoritative for publication, price, availability, and the Public Catalog JSON the storefront renders.

See [ADR 0011](adr/0011-meilisearch-derived-search.md).

## Why MySQL stays authoritative

Search can be stale, empty, or offline. Laravel always:

1. Asks Meilisearch (or the MySQL fallback) for ranked product/variant IDs.
2. Loads current public catalog projections from MySQL.
3. Drops IDs that are no longer public.
4. Returns existing ProductCard resources in search order.

A stale document cannot leak a draft, archived, or deleted product.

## Index list

Prefix comes from `MEILISEARCH_INDEX_PREFIX` (testing appends `_test` when the prefix does not already contain `test`). Schema version is `v1`.

| UID pattern | Purpose |
| --- | --- |
| `{prefix}_catalog_variants_{ka\|en}_v1` | Sellable variant documents, `distinctAttribute=product_id` |
| `{prefix}_categories_{ka\|en}_v1` | Public category suggestions |
| `{prefix}_brands_{ka\|en}_v1` | Public brand suggestions |

Rebuild UIDs add `_rebuild_{hex}`. After a successful swap the previous index is kept until the next rebuild.

## Variant-level documents

One document per public variant. Same-variant filtering uses `attribute_filter_keys` such as `attr_color_black`. Black+L only matches if a single variant has both keys. Product cards are deduplicated by `product_id`.

If a product has no explicit variants, the existing catalog still has a deterministic sellable unit (the default variant row). Search does not invent extra database variants.

## Document payload

Built by `SearchDocumentFactory` as readonly DTOs. Includes public name, slug, SKU, brand/category ids, ancestor ids, integer tetri prices, stock/sale/featured flags, merchandising/popularity scores, public media id (not filesystem paths), aliases (model number, barcode, opposite-locale brand name), and a document version hash. No supplier data, cost prices, HTML descriptions, or customer data.

## Ranking and filtering

Searchable attributes (priority): SKU, name, aliases, brand, category, attribute value codes, short description, searchable name.

Typo tolerance is on for names, off for SKU, with a higher minimum word size so short Georgian tokens are not over-corrected.

Public sorts stay the catalog allowlist: `default` (relevance), `featured`, `newest`, `price_asc`, `price_desc`, `name_asc`, `name_desc`. Popularity and merchandising are ranking signals, not arbitrary client sort fields.

Filters are built server-side from the existing catalog query contract. Clients never send Meilisearch filter strings.

Facet labels stay localized in MySQL. Counts for a search query use unique matching product IDs (cap 1000) then the existing facet query. Meilisearch 1.11 facet distribution alone would overweight variants.

## Environment

```text
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_MASTER_KEY=masterKey_change_me
MEILISEARCH_KEY=masterKey_change_me
MEILISEARCH_INDEX_PREFIX=outdoor_local
MEILISEARCH_CONNECT_TIMEOUT=0.4
MEILISEARCH_REQUEST_TIMEOUT=1.5
SEARCH_ENABLED=true
SEARCH_FALLBACK_ENABLED=true
SEARCH_DEFAULT_LOCALE=ka
```

Never put these in `NEXT_PUBLIC_*`. Docker Compose already runs Meilisearch with a persistent volume and health check on port 7700.

## Local startup

```bash
docker compose up -d meilisearch redis mysql
cd backend && php artisan search:configure && php artisan search:rebuild-catalog
php artisan queue:work --queue=media,catalog,default
```

The API container still boots if Meilisearch is stopped.

## Artisan commands

| Command | Role |
| --- | --- |
| `php artisan search:configure` | Create/update index settings |
| `php artisan search:rebuild-catalog` | Chunked rebuild + swap (`--locale=ka\|en`) |
| `php artisan search:sync-product {id}` | Rebuild one product from MySQL |
| `php artisan search:remove-product {id}` | Delete that product’s documents |
| `php artisan search:verify` | Report missing/unexpected docs (`--repair` to fix) |

Non-zero exit on infrastructure failure. Secrets are not printed.

## Public APIs

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/api/v1/catalog/products` | Unchanged contract. `q` present → Meilisearch + hydrate. `q` absent → existing MySQL list. |
| GET | `/api/v1/catalog/products/facets` | Same filters. Search `q` uses matching product IDs. |
| GET | `/api/v1/search?q=&locale=&limit=` | Grouped products, categories, brands |
| GET | `/api/v1/search/suggestions?q=&locale=&limit=` | Autocomplete; minimum two characters |

Pagination default remains 10. Rate limits: catalog list with `q` 20/min, grouped search 30/min, suggestions 60/min.

Admin (audited, `catalog.view` / `catalog.manage`): `/api/v1/admin/search/status`, configure, rebuild, product sync/remove, verify.

## Fallback

If Meilisearch is down and fallback is enabled, responses set `meta.fallback_used: true` and `search_mode: mysql_fallback`. Matching is limited to indexed name/SKU/brand/category columns — not HTML descriptions. If fallback is disabled, search endpoints return `503 SEARCH_UNAVAILABLE` without hostnames, keys, or stack traces. Category and product pages keep working.

## Synonyms

Locale-specific groups live in `config/search.php`. Seeded pairs are demonstration abbreviations only, not hunting law. Production synonyms should be edited in that config (or a later admin UI) and applied with `search:configure`. Do not auto-transliterate Georgian.

## Queue and consistency

Jobs are unique, retryable, and run after DB commit. Inventory updates are delayed (~15s) and coalesced. Expect search to lag writes by seconds, not to be a second inventory ledger.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Empty Meilisearch results, MySQL still finds `q` | Worker + `search:rebuild-catalog`; `meta.fallback_used` |
| Hidden product still in Meili | Hydration should hide it; `search:sync-product` / verify `--repair` |
| `SEARCH_UNAVAILABLE` | Host, master key, `SEARCH_FALLBACK_ENABLED` |
| Test indexes in local UI | Prefix must include `test` under `APP_ENV=testing` |

## Production / Hostinger

Meilisearch needs a persistent process and disk. Many shared-hosting plans cannot run it. Keep this architecture; deploy Meilisearch on a VPS, a managed host, or a sidecar VM. Do not expose port 7700 publicly. Day 16 does not include production deployment.

## Known limitations

- Distinct + Meilisearch 1.11 facets are variant-weighted; storefront facets use MySQL counts over unique product IDs (first 1000 hits).
- Georgian stemming is not claimed; matching uses prefix, typos, and configured aliases.
- Autocomplete does not invent “popular searches”.
- Search result URLs are `noindex, follow`.
