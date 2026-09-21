# ADR 0010: Client-side variant resolution from an authoritative public variant matrix

## Status

Accepted — Day 15

## Context

Product detail needs immediate SKU, media, price, promotion, and availability updates as the shopper selects color, size, and other axes. A request per click would add latency, break shareable URLs, and still could not make the browser the authority for GEL quotes or inventory. The Public Catalog API already returns a complete public variant matrix on the product-detail payload.

## Decision

1. **The product-detail response contains every publicly eligible variant.** Unpriced, archived, and otherwise ineligible variants are omitted server-side. Out-of-stock variants remain so the shopper can inspect them.

2. **Attribute clicks do not call the API.** `variant-resolver.ts` maps stable attribute/value codes onto combination keys (`color=black&size=m`). Pricing and availability on each combination are the backend snapshot from the detail request.

3. **Pricing and availability stay backend-calculated.** The storefront never computes discounts, never infers sale status, and never derives stock. It renders `final_amount_minor`, `applied_promotions`, and `availability.status` / `purchasable`.

4. **The shareable URL uses the public variant id** (`?variant=501`). Ids are already public in the matrix. Invalid ids fall back to the default variant without revealing hidden products. Updates use `replaceState` so option clicks do not flood history.

5. **The canonical URL stays product-level** without the variant query. Indexing a combinatorial explosion of variant URLs is unnecessary; Open Graph and Product structured data describe the product, with offers for priced variants.

6. **Cart on Day 17 must revalidate price and inventory.** The page cache TTL is 60 seconds (or the next price/promotion boundary). A `pricing_signature` on the purchase intent is a correlation hint, not a trusted price. Adding to cart must quote and reserve on the server.

7. **Out-of-stock variants remain visible and selectable.** Hiding them would make URLs and last-known combinations mysterious. Purchase is disabled until inventory and price allow it.

## Consequences

- The product page hydrates from one detail payload.
- Multi-axis products stay responsive without workers for bounded variant sets.
- Cached HTML can be briefly stale; Cart is the commerce authority.
- Day 16 search can keep using the same product route and `?variant=` contract.

## Alternatives considered

- **Per-click variant API:** rejected — waterfall, worse LCP/INP, duplicate authority.
- **Encode the full combination in the URL:** rejected — brittle, leaks structure, harder to share than a stable public id.
- **Canonical per variant:** rejected — thin duplicate pages unless a later SEO strategy requires it.
