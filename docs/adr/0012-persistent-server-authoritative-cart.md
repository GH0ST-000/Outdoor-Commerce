# ADR 0012: Persistent server-authoritative cart with secure guest identity

## Status

Accepted — Day 17

## Context

Day 15 defined an Add-to-Cart boundary that threw `CART_NOT_IMPLEMENTED`. Shoppers need a cart that survives refresh, works before login, merges after authentication, and is trustworthy enough for Day 18 checkout quoting.

A client-only cart (localStorage) would let the browser invent prices, quantities, and ownership. Redis-only carts would vanish on flush and would not be the MySQL source of truth this platform already uses for catalog, inventory, and pricing. Sequential cart IDs as guest credentials would be guessable (IDOR).

MySQL cannot express “one active cart per user” as a partial unique index. Naive inserts under concurrent requests would create duplicates.

Cart presence must not reserve inventory: reservations belong to checkout so abandoned carts do not freeze stock.

## Decision

1. **MySQL stores carts and lines.** Redis may help rate limits, sessions, and future locks. It is not the cart source of truth.

2. **Laravel is authoritative** for eligibility, current price, promotions, availability, quantity limits, totals, ownership, and merge. The storefront never computes canonical totals.

3. **Guest identity is an opaque high-entropy token** in an HttpOnly cookie. Only HMAC-SHA256(`token`, `APP_KEY`) is persisted. The public UUID is a log/API reference, not authorization.

4. **Prices are recalculated** on every read and mutation through `PublicCatalogPricing`. Price-at-add detects change; it is never the current selling price.

5. **Inventory is not reserved** during ordinary cart operations. Mutations validate current sellable availability. Checkout (Day 18) revalidates and reserves.

6. **Mutations use row locks and an integer `version`.** Stale `cart_version` returns HTTP 409 `CART_VERSION_CONFLICT`. Frontend replaces state; it does not loop retries.

7. **Mutations require `Idempotency-Key`**, scoped to the resolved customer/guest and endpoint, with a payload fingerprint and bounded TTL in MySQL.

8. **Guest carts merge after login/register** (HTTP auth controllers + explicit `POST /api/v1/cart/merge`). Matching variants sum then cap. The operation is idempotent. Arbitrary cart IDs are rejected.

9. **One active cart per user** is enforced with `lockForUpdate` in `CartResolver`, documented here because MySQL cannot enforce the partial unique constraint.

## Consequences

- Storefront `credentials: include` and CSRF cookies remain required for guest mutations (same-origin `/api` rewrite in local Next.js).
- Operators run `carts:expire` daily (scheduled). Hard-delete retention is a later policy.
- Checkout must not trust cart totals or availability without a fresh quote and reservation.

## Alternatives considered

- **localStorage cart:** rejected — client-invented prices/stock, lost ownership, no merge authority.
- **Redis as sole store:** rejected — not durable source of truth; flush loses carts.
- **Encrypt guest cookie with EncryptCookies:** rejected for this cookie because cart routes are not always inside the stateful Sanctum stack; an opaque token plus HMAC at rest is sufficient. Session cookies remain encrypted.
- **Reserve stock on add:** rejected — abandoned carts would lock inventory; Day 10 reservations stay checkout-owned.
