# Storefront visual design

Canonical token, component, and contribution rules: [design-system.md](design-system.md). Development gallery: `/dev/design-system` (blocked in production).

## Direction

**Caucasus Field Intelligence** — premium Georgian outdoor outfitter: deep pine and night forest surfaces, warm bone editorial sections, sparse copper accent, river-blue focus.

## Temporary brand

Configured in `src/features/storefront/config/brand.ts`. Replace when legal naming and logo assets are final.

## Fixture boundary

Demo catalog lives in `features/storefront/fixtures/` for field-guide articles, hunting-calendar preview rows, and search suggestions. Homepage merchandising, category landings, and product listings consume the Day 12 public catalog API through `features/catalog/api` and `features/catalog/adapters`. Legal demo rows always show `LegalDemoBanner` or the homepage disclaimer. See [storefront.md](storefront.md).

Product imagery uses `ResponsiveProductImage` / `ProductGallery` with a muted local placeholder at `/storefront/product-placeholder.svg` when no asset is available. Admin-uploaded derivatives are served from the backend `APP_URL` + `/storage/media/…` in development.

## Photography still needed

| Asset | Size | Subject |
| --- | --- | --- |
| Hero | 2400×1600 | Caucasus forest dawn / ridge mist |
| Category tiles | 1600×1200 each | Hunting, fishing, camping, clothing, optics, knives in real use |
| Product heroes | 1600×2000 | Jacket, optic, line, knife on neutral dark field |
| Map base | 2400×1600 | Stylized Georgia terrain (licensed) |
| OG image | 1200×630 | Brand + forest still |

Current `/public/storefront/*.svg` files are composition placeholders only.

## Motion

CSS variables `--duration-micro|control|panel|reveal` with `prefers-reduced-motion` kill switch. Hero uses `.sf-reveal` cascade; header solidifies on scroll; product cards lift on hover.
