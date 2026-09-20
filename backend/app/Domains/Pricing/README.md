# Pricing module

## Responsibility

List/sale prices, promotions, and checkout price quotes.

## Data owned

`price_lists`, `variant_prices`, `price_periods`, `promotions`, `promotion_targets`.

## Public contracts

- `Contracts/CheckoutPriceResolver` → `DefaultCheckoutPriceResolver` / `PriceQuoteService`
- Admin Actions under `Actions/PriceLists`, `Actions/Prices`, `Actions/Promotions`
- Admin Queries: `AdminPriceListListQuery`, `AdminPriceListQuery` / `AdminPriceIndexQuery`, `AdminPromotionListQuery`

## Events this module may publish

`PriceListActivated`, `PricePublished`, `PriceCancelled`, `PriceChanged`, `PromotionActivated`, `PromotionPaused`, `PromotionEnded`, `PromotionTargetsChanged` (dispatched after commit).

## May depend on

Shared (`Clock`); Catalog identifiers for promotion targets and readiness warnings.

## Explicitly outside this module

Inventory quantities, order persistence, FX conversion, tax calculation engines.

## Docs

See `docs/pricing.md` and ADR 0006.
