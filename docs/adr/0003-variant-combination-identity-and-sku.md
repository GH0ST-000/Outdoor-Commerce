# ADR 0003: Variant combination identity and SKU ownership

## Status

Accepted (Day 8)

## Context

Products need sellable configurations (color/size/magnification/reticle …) before inventory and pricing exist. Two questions had to be settled first:

1. What makes two variants "the same"?
2. Where does the SKU live?

Naive approaches fail quickly. Comparing localized labels breaks the moment a Georgian name is edited. Comparing a JSON blob of pairs breaks on key order. Putting the SKU on `products` makes every future price, stock, and cart row ambiguous for multi-variant products.

## Decision

1. **Combination identity is derived from IDs only.** A variant stores a canonical `combination_signature` (`"{attributeId}:{valueId}|…"`, sorted ascending by attribute ID) and its `combination_hash` (`sha256` of the signature). Labels, locales, and submission order never participate.
2. **Uniqueness is enforced in the database** with `unique(product_id, combination_hash)`, and pre-checked in the domain so the API answers 422 with an actionable message instead of surfacing a driver error.
3. **The empty combination is legal exactly once.** A product with zero axes has one variant whose signature is `""`. This keeps single-configuration products on the same code path as multi-axis products — there is no "product without variants" special case downstream.
4. **SKU lives on `product_variants`, never on `products`,** is globally unique (including soft-deleted rows), and is normalized to uppercase `A-Z 0-9 - _`.
5. **Generated SKUs are sequential per product:** `PRD-{productId}-{seq}` with a 3-digit sequence. Allocation happens while the product row is locked, then steps forward past sequences already claimed by manually entered SKUs, with bounded retries.
6. **Barcodes are optional GTINs** validated with the GS1 check digit and kept globally unique, separate from SKU.
7. **Exactly one default variant per product** among non-archived variants. The first variant created becomes it; archiving the default requires an explicit `replacement_variant_id` in the same transaction unless it is the last variant.
8. **Archiving soft-deletes,** matching Day 7 product archiving. Archived variants keep their combination slot, so re-creating that combination is refused with a pointer to the archived variant rather than silently colliding.
9. **Axis changes cannot break existing variants.** Removing an axis used by non-archived variants, or adding an axis while active variants exist, fails with `VARIANT_AXIS_CONFLICT` listing the affected attributes and variants.
10. **Activation requirements moved but never auto-demote.** A product now needs an active default variant to be activated; an already-active product that regresses reports `warnings` from `evaluate()` and keeps its status until an admin changes it.

## Consequences

- Reordering the UI's axis inputs can never create duplicate variants.
- Renaming a value's Georgian or English label never changes identity, so downstream references stay stable.
- Inventory, pricing, cart, and order lines can safely key on `product_variants.id` from day one, including for single-configuration products.
- The `unique(product_id, combination_hash)` constraint spans soft-deleted rows, so "restore the archived variant" is the intended path rather than re-creating it. This is a deliberate trade for guaranteed identity stability.
- Sequential SKU generation is serialized per product by the product row lock. This is fine for admin-scale writes; a bulk importer would want a dedicated sequence table instead.
- Because active variants must cover every axis, adding an axis to a live product is a deliberate two-step operation (demote/archive, then add).
