# Storefront visual design

## Direction

**Caucasus Field Intelligence** — premium Georgian outdoor outfitter: deep pine and night forest surfaces, warm bone editorial sections, sparse copper accent, river-blue focus.

## Temporary brand

Configured in `src/features/storefront/config/brand.ts`. Replace when legal naming and logo assets are final.

## Fixture boundary

Demo catalog lives in `features/storefront/fixtures/`. Adapters in `features/storefront/adapters/` are the swap point for the future public catalog API. Legal demo rows always show `LegalDemoBanner`. Prices/stock are labeled non-authoritative.

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
