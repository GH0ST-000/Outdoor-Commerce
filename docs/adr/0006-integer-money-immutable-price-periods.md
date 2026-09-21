# ADR 0006: Integer money and immutable price periods

## Status

Accepted (Day 11)

## Context

Storefront and checkout must agree on prices. Floating-point and in-place edits to historical prices cause reconciliation and audit failures.

## Decision

- Money is stored and calculated in **integer minor units** per currency configuration.
- Variant-level pricing lives in the Pricing domain (`variant_prices` + `price_periods`).
- **Published** price periods are append-only; schedule changes add new periods or explicit cancel/supersede operations.
- Effective windows use UTC storage with **inclusive start** and **exclusive end**.
- Promotions are evaluated on the server; checkout must revalidate quotes later.

## Consequences

- No automatic FX conversion between currencies.
- Overlap prevention requires transactional checks (MySQL row locks), not DB constraints alone.
- Cache (Redis) is a performance layer only; MySQL remains authoritative.
