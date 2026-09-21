# ADR 0009: URL-driven storefront filters and server-first category rendering

## Status

Accepted — Day 14

## Context

The public catalog already exposes a stable filter contract (`brand`, `attribute[code]`, integer GEL tetri, `in_stock`, `on_sale`, `sort`, `page`). The first storefront listing kept that state in React and re-fetched after hydration. That hid products from the initial HTML, broke copy-paste URLs, and made back/forward unreliable. Homepage merchandising still used fixture prices.

## Decision

1. **The URL is the filter authority.** Listing pages parse the query string on the server, load Public Catalog API results, and render HTML that matches the URL. Client controls call `router.push` with a normalized query; they do not keep a second product list.

2. **Initial results are server-rendered.** Homepage sections, category heroes, breadcrumbs, the first product grid, facets, and metadata run in React Server Components. Client islands cover the filter drawer, sort control, layout toggle, and CTA tracking.

3. **Mobile filters use draft state until Apply.** Desktop checkboxes update the URL immediately. The mobile sheet edits a copy of the query and commits on Apply so each checkbox does not start a navigation on a small viewport. Escape, focus trap, and restoration come from the existing drawer primitive.

4. **Arbitrary filtered URLs are `noindex, follow`.** Base category pages and unfiltered pagination may be indexed. Search, price/attribute combinations, and empty result pages are not. Canonical URLs drop filter parameters. This prevents a combinatorial index of facet URLs.

5. **Homepage optional sections fail independently.** `getHomePageData` uses `Promise.allSettled`. Category navigation is critical; featured products, on-sale, and brands may omit themselves after a logged warning. The page does not return 500 because one merchandising request failed.

6. **Map and legal teasers are labeled previews.** Day 14 has no hunting-period or GIS authority. Those sections use generic copy, a visible “interface preview” label, and a disclaimer. They do not emit species, seasons, limits, or boundaries as structured data.

## Consequences

- Copying a listing URL reproduces the same products after refresh.
- Locale switching can `router.refresh()` against the same path; alternate paths come from the API when present.
- Next.js `revalidate: 60` aligns with the public catalog Redis TTL and must not outlive scheduled price boundaries.
- Day 15 product detail can keep the same card, price, and availability contract.
- Day 16 Meilisearch can replace `q` and facet counts without changing the storefront query parser.

## Alternatives considered

- **Client-only filters:** rejected — poor SEO, duplicate fetches, broken history.
- **Index every facet URL:** rejected — unbounded thin pages.
- **Authoritative-looking demo seasons on the homepage:** rejected — legal risk before the hunting module exists.
