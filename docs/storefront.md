# Storefront homepage and category listing (Day 14)

The storefront homepage, catalog root, and category landing pages are server-first. They read the Public Catalog API through `features/catalog/api/public-catalog-client.ts` and never import Laravel models.

## Homepage architecture

`app/(storefront)/page.tsx` loads locale from the `outdoor-locale` cookie (default **ka**), composes data, and renders `HomeView`.

Composer: `features/home/api/get-home-page-data.ts`.

| Section | Source | Failure |
| --- | --- | --- |
| Hero | Localized config + local/approved hero image | Critical visual; page still renders |
| Category gateway | `GET /categories` mapped to configured slugs | Critical — error + retry |
| Featured gear | `GET /products?featured=true` | Omit or compact retry |
| Seasonal teaser | Static localized copy | Always informational |
| On-sale | `GET /products?on_sale=true`, cards only if `price.on_sale` | Omit when empty/failed |
| Map teaser | Static copy + local image, labeled preview | Designed fallback image |
| Brands | `GET /brands` | Omit |
| Field journal | Editorial fixtures in `fixtures/demo-catalog.ts` | Omit unavailable articles |
| Trust | Config (`features/home/content`) | Always present |
| Newsletter | Visual CTA, disabled | Never submits |

Optional requests use `Promise.allSettled`. Failures log through `features/storefront/lib/log.ts`.

## Localized content

Copy lives in `features/home/content/{ka,en}.ts`. Day 32 can replace this with managed content by keeping the same `HomePageContent` shape. Do not put long copy objects in page JSX.

## Category routes

Cookie locale, not `/[locale]` prefixes:

```text
/
/catalog
/catalog/[categorySlug]
/brands/[brandSlug]
```

Invalid or non-public categories return `notFound()` (404). Category canonical paths come from the API when present.

## URL filter contract

Authoritative query string, normalized by `features/catalog/query-state/catalog-search-params.ts`:

```text
/catalog/hunting?brand=brand-a&attribute[color]=black&min_price=10000&sort=price_asc&page=2
```

| Param | Notes |
| --- | --- |
| `brand` | Repeatable, stable brand codes/slugs |
| `attribute[code]` | Repeatable values; OR within an attribute, AND across attributes |
| `min_price` / `max_price` | Integer GEL tetri |
| `in_stock` / `on_sale` / `featured` | `1` when true |
| `q` | Bounded catalog search |
| `sort` | `default` `featured` `newest` `price_asc` `price_desc` `name_asc` `name_desc` |
| `page` | 1-based; omitted when 1 |
| `per_page` | Always 10 from the storefront |

Changing a filter or sort resets `page` to 1. Unknown params are ignored. Price inputs accept major GEL (`12.50`) in the UI and convert with integer parsing in `query-state/gel.ts` — never `parseFloat * 100`.

Desktop checkboxes update the URL immediately. The mobile bottom sheet keeps draft state until **Apply**.

## SEO

- Base category: index, follow; canonical is the clean path.
- Unfiltered `?page=N`: self-canonical, indexable.
- Any filter or search combination: `noindex, follow`; canonical strips filters.
- Empty results: `noindex, follow`.
- Homepage: Organization + WebSite JSON-LD.
- Category: BreadcrumbList + ItemList (name, url, position). No fake ratings. No Product offers here (Day 15).
- Legal teasers are not represented as authoritative structured data.

## Cache / revalidation

All public catalog fetches use `next: { revalidate: 60, tags: ["public-catalog"] }`, matching the API TTL (`min(60s, next price/promotion boundary)`). Locale is part of the request headers. Error responses are not stored as successful page data; failed sections return `null` and omit UI.

On-demand revalidation can later call `revalidateTag("public-catalog")` from a catalog domain webhook.

## Analytics

Event names live in `features/storefront/analytics/events.ts`. `trackStorefrontEvent` is a no-op until Day 33. Do not add a third-party snippet on these pages.

## Testing

```bash
cd frontend
npm run test:run
npm run lint -- --max-warnings=0
npm run typecheck
npm run build
```

There is no Playwright, visual, or Storybook job in this repository yet.

## Day 15

Product detail is documented in [product-detail.md](product-detail.md). Listing cards keep consuming public `href`, GEL price range, promotion flags, availability, and ready media. The PDP adds `?variant=` selection from the public matrix without inventing ratings or stock numbers.
