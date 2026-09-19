# Catalog / Product Core

## Overview

Day 7 introduces the **Product** content aggregate in the Catalog domain.

**Important:** `active` product status means Day 7 content readiness only. It does **not** mean the product is purchasable.

```text
Active product != purchasable product
```

Purchasability requires later days (variants/SKU, media, inventory, pricing).

## Day 6 minimum foundation (required for Day 7)

Day 6 full category/brand admin UIs were not present in the repository. Day 7 includes the minimum Category/Brand tables, models, factories, selector APIs, and demo seeder so products can assign organization data.

## Tables

### `products`
Core identity: optional `brand_id`, optional `primary_category_id` (required when active), `status` (`draft|active|archived`), model/MPN (not SKUs), `is_featured`, `sort_order`, `published_at`, `created_by`/`updated_by`, soft deletes.

### `product_translations`
Localized `name`, `slug`, `short_description`, sanitized HTML `description`, SEO fields. Unique `(product_id, locale)` and `(locale, slug)`.

### `category_product`
Pivot with `sort_order`. Primary category must be included in assignments (domain-enforced).

### Supporting Day 6 tables
`categories`, `category_translations`, `brands`, `brand_translations` with soft deletes and `draft|active|archived` status.

## Locales

Configured in `config/catalog.php`: `ka` (default/fallback), `en` (optional).

## Description format

Sanitized HTML allowlist via `HtmlContentSanitizer` (no scripts, no event handlers, unsafe URLs stripped).

## Publishing readiness

`ProductReadinessService` requires: Georgian translation with name/slug, active primary category included in assignments, non-deleted categories, active brand if set, product not deleted.

Since Day 8 it additionally requires at least one active variant and exactly one default variant that is itself active. See [docs/catalog-variants.md](catalog-variants.md).

## Archive / restore

Archive soft-deletes and sets status `archived`. Restore returns the product to **`draft`** (never auto-activates).

## Permissions

| Permission | Product capability |
| --- | --- |
| `catalog.view` | List/detail/readiness/options |
| `catalog.manage` | Create/update/archive/restore |
| `catalog.publish` | Activate / set status to active |

## Admin API

See `docs/api-conventions.md`. Base: `/api/v1/admin/products`.

Selectors: `/api/v1/admin/catalog/options/{categories,brands}`.

## Cache

`CatalogCache` increments `catalog:cache_version` **after** successful write commits (for Day 12 public catalog).

## Seed

```bash
php artisan db:seed --class=CatalogDemoSeeder
```

Local/testing only. No prices/SKUs/inventory/media.

## Day 8: variants, attributes, SKUs

Delivered in [docs/catalog-variants.md](catalog-variants.md) and [ADR 0003](adr/0003-variant-combination-identity-and-sku.md). SKUs live on `product_variants` — do not treat `model_number` / MPN as SKUs.
