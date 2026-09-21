# Catalog

Owns products, categories, brands, variants, attributes, and media metadata.

## Day 7 public surface

- Contracts: `CatalogLocales`, `CatalogProductLookup` (+ `DTOs/CatalogSellableRefData`)
- Models: `Product`, `ProductTranslation`, `Category`, `Brand` (+ translation models)
- Enums: `ProductStatus`, `CatalogStatus`
- Actions under `Actions/Products/*`
- Queries under `Queries/Products/*`
- `Services/CatalogCache`, `Services/Products/*`, `Services/EloquentCatalogProductLookup`
- `Support/*` (locales, slug, HTML sanitizer) — internal; prefer `Contracts/CatalogLocales` cross-module
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

## Day 9 public surface

- Models: `MediaAsset`, `MediaDerivative`, `MediaAttachment`, `MediaAttachmentTranslation`
- Enums: `MediaStatus`, `MediaPreset`, `MediaFormat`, `MediaDisk`, `MediaAttachmentRole`
- Actions under `Actions/Media/*`
- Services under `Services/Media/*`
- Job: `App\Jobs\ProcessMediaAsset` (queue `media`)
- Commands: `media:cleanup-orphans`, `media:recover-stuck`
- Policy: `MediaAttachmentPolicy`
- Disks: `media_private` (originals), `media_public` (derivatives)

Docs: [docs/catalog-media.md](../../../../docs/catalog-media.md), [ADR 0004](../../../../docs/adr/0004-local-media-storage-and-async-derivatives.md).

## Day 12 public catalog

- `PublicApi/` query services, eligibility, presenters, projections, cache
- HTTP: `GET /api/v1/catalog/{categories,brands,products,products/facets,products/{slug}}`
- Commands: `catalog:rebuild-public-projections`, `catalog:verify-public-projections`, `catalog:refresh-time-sensitive-projections`
- Docs: [docs/public-catalog.md](../../../../docs/public-catalog.md), [ADR 0007](../../../../docs/adr/0007-rebuildable-public-catalog-projections.md)

## Depends on

- `Shared`
- `Operations` Actions/DTOs/Enums for audit recording
- `Identity` Enums only for permission names in policies (not Identity models)
- `intervention/image` (GD) for derivative generation

## Explicitly outside Day 9

Cloud object storage, CDN, inventory, pricing, public storefront catalog API.
