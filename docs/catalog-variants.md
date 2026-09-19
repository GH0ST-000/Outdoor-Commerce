# Catalog / Variants, Attributes, SKUs, Barcodes

## Overview

Day 8 adds the sellable layer under the Day 7 product aggregate:

```text
Product (content)  →  ProductVariant (sellable configuration)
                        ├─ sku          (unique, lives here — never on Product)
                        ├─ barcode      (optional GTIN, unique)
                        └─ combination  (one attribute value per variant axis)
```

`model_number` and `manufacturer_part_number` on `Product` are **not** SKUs.

Day 8 still does not make anything purchasable: prices, stock, and media arrive later.

## Tables

| Table | Purpose |
| --- | --- |
| `attributes` | Axis definitions (`select`, `color`), unique `code`, `draft/active/archived`, soft deletes |
| `attribute_translations` | Localized `name` / `description`, unique `(attribute_id, locale)` |
| `attribute_values` | Values per attribute, unique `(attribute_id, code)`, optional `color_hex`, `metadata` |
| `attribute_value_translations` | Localized value labels |
| `product_attributes` | Which attributes a product varies by, with `sort_order` (the *axes*) |
| `product_variants` | `sku` (unique), `barcode` (unique), status, `is_default`, `combination_hash`, `combination_signature` |
| `product_variant_attribute_values` | One row per (variant, attribute, value); one value per attribute per variant |

## Combination identity

Identity comes from IDs only — never labels, never locale.

```text
signature = "{attributeId}:{valueId}|…"   sorted ascending by attribute ID
hash      = sha256(signature)
```

- Order of submitted pairs is irrelevant: `color→black, size→m` and `size→m, color→black` produce the same signature and hash.
- A product with **zero axes** has exactly one legal combination: the empty one (`signature = ""`). A second empty variant is rejected.
- Uniqueness is enforced by `unique(product_id, combination_hash)` and pre-checked in the domain so the API returns 422 with a readable message instead of a database error.
- Soft-deleted (archived) variants keep their slot. Creating the same combination again reports which archived variant owns it and suggests restoring instead.

See [ADR 0003](adr/0003-variant-combination-identity-and-sku.md).

## SKU

- Normalized to uppercase; allowed characters `A-Z 0-9 - _`; max length from `catalog.variants.sku.max_length`.
- Globally unique, including soft-deleted variants.
- Generated when omitted: `PRD-{productId}-{seq}` with a 3-digit sequence (`PRD-12-001`).
- Generation holds the product row lock, then walks forward past any sequence already taken by a manual SKU (bounded retries).

## Barcode

Optional GTIN-8/12/13/14. Digits only (spaces and hyphens are stripped), GS1 check digit validated, globally unique. Blank input clears the barcode. Example valid EAN-13: `4006381333931`.

## Statuses and invariants

| Rule | Behavior |
| --- | --- |
| Georgian translation | Required before an attribute or value can become `active` |
| `color_hex` | Only on `color` attributes; normalized to `#RRGGBB` uppercase |
| Attribute `code` / `type` | Frozen once the attribute has values, variants, or product axes |
| Active variant | Needs exactly one active value per product axis |
| Default variant | Exactly one per product among non-archived variants; the first variant created becomes it |
| Archived variant | Can never be the default |
| Archiving the default | Requires `replacement_variant_id` in the same transaction (unless it is the last variant) |
| Restore | Always returns to `draft`; never auto-activates |

Archiving an attribute, value, or variant sets status `archived` **and** soft-deletes the row, matching product archiving. Attributes and values still used by non-archived variants cannot be archived.

## Variant axes

`product_attributes` is replaced wholesale via `PUT /variant-axes`.

- Removing an axis that non-archived variants still resolve fails with `VARIANT_AXIS_CONFLICT`, listing the offending `attribute_ids` and `variant_ids`.
- Adding an axis while **active** variants exist fails the same way: those variants would silently lose the one-value-per-axis guarantee. Demote or archive them first.

## Generation

`POST /variants/generate-preview` is strictly read-only — no writes, no audit event, no cache bump. It returns the full Cartesian plan with `total_combinations`, `existing_count`, `new_count`, `exceeds_limit`, and `truncated` (the `combinations` list is capped at the limit for display).

`POST /variants/generate` runs in one transaction, skips combinations that already exist, generates sequential SKUs, records a single `product_variants.generated` summary audit event, and bumps the catalog cache after commit.

Limits live in `config/catalog.php`:

```php
'variants' => [
    'max_combinations_per_generation' => 100,
    'max_variants_per_product' => 500,
],
```

Exceeding the per-call limit throws `COMBINATION_LIMIT_EXCEEDED` with `{count, limit}` in `error.details`.

Generation requires at least one axis and one active value per axis; zero-axis products use the single-variant create endpoint instead.

## Readiness

`VariantReadinessService` checks a single variant: valid SKU, live parent product, one active value per current axis, all referenced attributes and values active and non-deleted.

`ProductReadinessService` now also requires, on top of the Day 7 content rules:

1. at least one active, non-deleted variant,
2. exactly one default variant,
3. that default being active and non-deleted.

`evaluate()` never mutates the product. For a product that is already `active` but has regressed (for example its last variant was archived), the same findings are echoed under `warnings` — status demotion stays an explicit admin action. `assertReadyForActivation()` blocks new activations.

## Permissions

| Permission | Capability |
| --- | --- |
| `catalog.view` | List/detail for attributes, values, variants, axes, generation preview |
| `catalog.manage` | Create/update/archive/restore, axis sync, generate |
| `catalog.publish` | Setting any of them to `active` |

Policies: `AttributePolicy`, `AttributeValuePolicy`, `ProductVariantPolicy` — registered in `AppServiceProvider`.

## Admin API

Base: `/api/v1/admin`. See `docs/api-conventions.md` for the envelope.

```text
GET    /attributes                                   ?search=&status=&type=&is_filterable=&locale=&sort=&per_page=
POST   /attributes
GET    /attributes/{attribute}
PATCH  /attributes/{attribute}
PATCH  /attributes/{attribute}/status
DELETE /attributes/{attribute}
POST   /attributes/{attribute}/restore

GET    /attributes/{attribute}/values
POST   /attributes/{attribute}/values
GET    /attributes/{attribute}/values/{value}
PATCH  /attributes/{attribute}/values/{value}
PATCH  /attributes/{attribute}/values/{value}/status
DELETE /attributes/{attribute}/values/{value}
POST   /attributes/{attribute}/values/{value}/restore

GET        /products/{product}/variant-axes
PUT|PATCH  /products/{product}/variant-axes

POST   /products/{product}/variants/generate-preview
POST   /products/{product}/variants/generate

GET    /products/{product}/variants                  ?search=&status=&is_default=&attribute_id[]=&attribute_value_id[]=
POST   /products/{product}/variants
GET    /products/{product}/variants/{variant}
PATCH  /products/{product}/variants/{variant}
PATCH  /products/{product}/variants/{variant}/status
POST   /products/{product}/variants/{variant}/default
DELETE /products/{product}/variants/{variant}        { "replacement_variant_id": 42 }
POST   /products/{product}/variants/{variant}/restore
```

Nested routes verify ownership: a value that does not belong to `{attribute}`, or a variant that does not belong to `{product}`, returns **404** rather than leaking the resource.

Lists default to `per_page=10` with a maximum of 50, and sorting is restricted to a whitelist.

### Variant payload

```json
{
  "sku": "OPTIC-3940-MIL",
  "barcode": "4006381333931",
  "status": "active",
  "sort_order": 0,
  "is_default": true,
  "attribute_values": [
    { "attribute_id": 7, "attribute_value_id": 31 },
    { "attribute_id": 9, "attribute_value_id": 44 }
  ]
}
```

### Generation payload

```json
{
  "status": "draft",
  "axes": [
    { "attribute_id": 7, "attribute_value_ids": [31, 32] },
    { "attribute_id": 9, "attribute_value_ids": [44, 45] }
  ]
}
```

## Audit events

`attribute.created|updated|status_changed|archived|restored`,
`attribute_value.created|updated|status_changed|archived|restored`,
`product.variant_axes_updated`,
`product_variant.created|updated|status_changed|default_changed|archived|restored`,
`product_variants.generated` (one summary row per generate call).

All are written inside the same transaction as the change; `CatalogCache::bump()` runs only after commit.

## Seed

```bash
php artisan db:seed --class=CatalogDemoSeeder
```

Local/testing only, and idempotent. Seeds `color`, `size`, `length`, `magnification`, and `reticle` with `ka`/`en` labels and values, a multi-axis demo optic (magnification × reticle, four active variants, one default), and a zero-axis demo knife with a single empty-combination default variant. No barcodes, prices, stock, or media.
