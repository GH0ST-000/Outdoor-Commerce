# Catalog

Owns products, categories, brands, variants, attributes, and (later) media metadata.

## Day 7 public surface

- Models: `Product`, `ProductTranslation`, `Category`, `Brand` (+ translation models)
- Enums: `ProductStatus`, `CatalogStatus`
- Actions under `Actions/Products/*`
- Queries under `Queries/Products/*`
- `Services/CatalogCache`, `Services/Products/*`
- `Support/*` (locales, slug, HTML sanitizer)
- `Policies/ProductPolicy`

## Day 8 public surface

- Models: `Attribute`, `AttributeValue`, `ProductVariant`, `ProductVariantAttributeValue` (+ translation models)
- Enums: `AttributeType`, `AttributeStatus`, `AttributeValueStatus`, `ProductVariantStatus`
- Actions under `Actions/Attributes/*`, `Actions/AttributeValues/*`, `Actions/Variants/*`
- DTOs under `DTOs/Attributes/*`, `DTOs/Variants/*`
- Queries under `Queries/Attributes/*`, `Queries/Variants/*`
- `Services/Attributes/*`, `Services/Variants/*`
- `Support/AttributeCode`, `Support/ColorHex`, `Support/Variants/{Sku,Barcode,VariantCombination}`
- Exceptions: `CombinationLimitExceededException`, `VariantAxisConflictException`, `VariantLimitExceededException`
- Policies: `AttributePolicy`, `AttributeValuePolicy`, `ProductVariantPolicy`

Docs: [docs/catalog-variants.md](../../../../docs/catalog-variants.md), [ADR 0003](../../../../docs/adr/0003-variant-combination-identity-and-sku.md).

SKUs live on `product_variants`. `Product.model_number` / MPN are not SKUs.

## Depends on

- `Shared`
- `Operations` Actions/DTOs/Enums for audit recording
- `Identity` Enums only for permission names in policies (not Identity models)

## Explicitly outside Day 8

Media, inventory, pricing, public storefront catalog API.
