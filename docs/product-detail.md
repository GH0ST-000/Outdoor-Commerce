# Product detail page (Day 15)

The product detail route is server-first. `app/(storefront)/products/[productSlug]/page.tsx` loads one Public Catalog product, optional brand copy, and related cards, then hydrates a small client island for gallery, variant selection, quantity, share, and the mobile purchase bar.

## Server / client boundary

| Server | Client island |
| --- | --- |
| `generateMetadata`, canonical, hreflang, robots | Gallery swipe, thumbnails, fullscreen viewer |
| Product JSON-LD and BreadcrumbList | Variant clicks and `?variant=` URL writes |
| 404 via `notFound()`, transient errors via `error.tsx` | Quantity stepper |
| Related products (same primary category) | Purchase button (feature-flagged) |
| Brand block when the public brand exists | Share (Web Share API or clipboard) |

`loadProductDetail` is wrapped in React `cache()` so `generateMetadata` and the page share one request.

## API contract

`GET /api/v1/catalog/products/{slug}` already returns the fields this page needs: localized name/slug, sanitized description, brand, categories, breadcrumbs, product gallery, public variant matrix (axes + combinations with media, GEL quotes, promotions, availability), default variant, SEO, and alternate locale paths. The storefront does not call admin endpoints or recompute price or stock.

Unpriced variants are absent from the matrix. Out-of-stock variants remain visible with `purchasable: false`.

## Variant selection

Pure resolver: `features/product-detail/state/variant-resolver.ts`.

1. Combination identity is `axisCode=valueCode` pairs sorted by axis code. Order of attributes in the payload does not change identity.
2. Initial variant: URL `?variant={id}` if that id belongs to the product, else public default, else first combination.
3. Invalid URL ids fall back and the query is replaced.
4. Clicking a value keeps the current other axes when that combination exists. If it does not, the resolver picks the candidate that shares the new value and the most other selected axes, preferring purchasable then default then lowest id.
5. A value is **invalid** only when no public combination contains it. A value is **out of stock** when the combination you would land on is not purchasable. Out-of-stock options stay selectable.

Attribute clicks do not fetch. Pricing and availability on each combination are backend-calculated snapshots from the detail response.

## Variant URL

```text
/products/{slug}?variant=501
```

Updates use `history.replaceState` so intermediate clicks do not stack history. Refresh and shared links restore the variant. Locale switch navigates to `alternate_locale_paths[locale]` and keeps `?variant=` when the id is still valid.

The **canonical URL is the product path without the variant query**. Variant is a shareable selection, not an indexable URL.

## Gallery and media

1. Selected variant ready media when the combination has its own gallery.
2. Otherwise product gallery from the same detail payload.
3. Otherwise the local placeholder (`/storefront/product-placeholder.svg`).

Derivatives: thumbnail rail uses `thumbnail`, main stage uses `detail`, fullscreen viewer loads `zoom` only after open. The first visible image may be `priority`. Hidden images are lazy. AVIF is not synthesized; only API-provided WebP/JPEG sources are rendered.

## Price, promotion, availability

`PriceDisplay` and `AvailabilityStatus` render API fields. The browser never calculates a discount or a stock count. Missing price shows the localized “on request” state, never `0`. Promotions are the `applied_promotions` names returned with the quote. Exact on-hand quantity is not shown.

## Quantity and purchasability

Quantity is an integer UI control, min `1`, max `PRODUCT_QUANTITY_UI_MAX` (12). That ceiling is not inventory. Cart (Day 17) must validate again.

Purchasable when the selected variant has a final price and `availability.purchasable`. Otherwise the action is disabled with an explanation.

## Day 17

Implemented. See [cart.md](cart.md). `NEXT_PUBLIC_CART_ENABLED=true`. The product page sends an idempotent add against Laravel; quantity on the page is a hint. Do not show success until the server confirms.

## SEO and structured data

- Title/description from public SEO, falling back to product name + brand and short description.
- Canonical = `canonical_path` (no `variant`).
- `alternates.languages` from `alternate_locale_paths`.
- Open Graph image from `seo.open_graph_media` or the first ready gallery image (detail/card, never private originals).
- Product JSON-LD: real SKU, GEL decimal strings from integer tetri, schema.org availability, AggregateOffer when multiple priced variants exist. Unpriced variants omitted. No reviews or aggregate ratings.
- BreadcrumbList matches the visible trail (Home → category ancestors → product).

## Cache / revalidation

`export const revalidate = 60` and fetch `next: { revalidate: 60, tags: ["public-catalog"] }`, matching the public catalog TTL (`min(60s, next price/promotion boundary)`). Locale is a request header. `notFound()` is used for ineligible products; upstream 5xx throws into the route error boundary and is not stored as a successful product page. Day 17 Cart must ignore this page cache and revalidate price and inventory.

## Accessibility

One `h1`. Variant groups are fieldsets with the current value in the legend. Color swatches expose names. Invalid options are `disabled`; out-of-stock options are `unavailable` but selectable. Price and availability use polite live regions. Fullscreen viewer is a Radix dialog (focus trap, Escape, restore). The mobile purchase bar is `aria-hidden` so it does not duplicate the primary action for assistive tech. Georgian is never forced to uppercase.

## Testing

```bash
cd frontend
npx vitest run src/features/product-detail src/features/storefront/tests/ProductDetailPage.test.tsx src/features/storefront/tests/ProductGallery.test.tsx
npm run lint -- --max-warnings=0
npm run typecheck
npm run build
```

There is no Playwright, visual, or Storybook job in this repository yet.

## Day 17

Implemented. See [cart.md](cart.md). The product page sends an idempotent add; quantity is a hint. Success is not shown until Laravel confirms.
