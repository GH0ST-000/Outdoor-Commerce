# ADR 0002: Product Core boundaries and primary category modeling

## Status

Accepted (Day 7)

## Context

The catalog needs a product content aggregate before variants, media, inventory, and pricing exist.

## Decision

1. Use normalized `product_translations` tables (not JSON) for `ka`/`en`.
2. Allow multiple categories via `category_product`, with one explicit `primary_category_id`.
3. Make brand optional (`brand_id` nullable).
4. Treat `active` as content-ready only — not purchasable.
5. Keep variants, SKUs, media, prices, and inventory outside Day 7.
6. Sanitize HTML descriptions with an allowlist sanitizer.

## Consequences

- Day 12 public catalog must still verify related brand/category validity for visibility.
- Day 8 can attach variants/SKUs without rewriting product identity.
- Day 6 category/brand admin UIs can be expanded later; Day 7 ships selector APIs + tables.
