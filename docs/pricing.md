# Pricing engine (Day 11)

Integer minor-unit money, price lists, immutable published price periods, and backend promotion calculation.

## Money

- Store amounts as `amount_minor` integers with ISO `currency` (GEL primary).
- Never use floats for business math.
- Percentage discounts use basis points (100 = 1%); rounding is half-up to the minor unit.

## Price lists & periods

- `price_lists` → `variant_prices` → `price_periods`.
- Effective base price: published period where `starts_at <= now` and (`ends_at` is null or `ends_at > now`).
- Boundaries: **inclusive start**, **exclusive end**.
- Published periods are immutable; corrections use new periods or cancel/supersede flows.
- Overlapping published periods are rejected under row lock on `variant_prices`.

## Promotions

- Targets use string aliases (`product`, `category`, …), not PHP class names in the database.
- Category match is **direct assignment only** (no descendant roll-up).
- Exclusions override inclusions.
- Stacking: best exclusive vs sequential combinable (% then fixed unless priority overrides), capped by `pricing.promotions.max_combinable`.

## Contracts

- `CheckoutPriceResolver` → `DefaultCheckoutPriceResolver` / `PriceQuoteService` for cart/checkout consumers.

## Configuration

- `config/currencies.php`, `config/pricing.php`
- Admin timezone: `currencies.admin_timezone` (Asia/Tbilisi)

## Tests

```bash
cd backend
PAO_DISABLE=true vendor/bin/pest tests/Feature/Pricing tests/Unit/Domain/Pricing
```
