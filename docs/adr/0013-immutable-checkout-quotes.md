# ADR 0013: Immutable checkout quotes with short-lived inventory reservations

## Status

Accepted — Day 18

## Context

The Day 17 cart is a shopping workspace. It recalculates current prices and checks availability, but it does not reserve stock, capture a delivery address, or freeze a payable total. Creating an order directly from a cart would let prices, promotions, stock, or fulfillment change between “view cart” and “pay”, and abandoned carts would lock inventory if reservations happened at add-to-cart.

A mutable quote row updated in place would rewrite history. A browser-calculated total would not be reconcilable. Redis-only holds would not survive a flush and are not the MySQL source of truth.

## Decision

1. **Checkout session** is the mutable workflow (contact, address, fulfillment, version, expiry). Ownership follows Day 17 (Sanctum user or HMAC of the guest cart cookie). The public UUID is not authorization.

2. **Quote** is an immutable revision: current sellability, `PublicCatalogPricing`, configured delivery rates, tax representation, fingerprint, and reservation keys. Recalculation inserts a new revision and supersedes the previous active one.

3. **Inventory is reserved only when a quote is created**, through Day 10 `CheckoutInventoryService`. Creation is atomic across lines. Cart activity does not reserve. Reservations expire with the quote (default 15 minutes) and are released on supersede, cancel, and expire. They are not sales (`on_hand` unchanged until Day 20 payment commit). Day 19 reassigns active quote holds to the pending-payment order.

4. **Laravel totals are authoritative.** The API prohibits client money fields. Integer minor units. Default `price_includes_tax=true` with `tax_total_minor=0` until a tax engine exists.

5. **MySQL remains the source of truth.** Idempotency records, sessions, quotes, and rates are relational. Redis may rate-limit; it does not decide stock.

6. **Day 19 consumes the stored active quote**, not a payload echoed from the browser.

## Consequences

- Shoppers must complete contact + fulfillment before quoting.
- Quote refresh is bounded; TTL does not extend on page load.
- Fulfillment admin UI is deferred; methods and rates are seeded/configured.
- Pickup does not allocate a store-specific warehouse beyond Day 10 default allocation.

## Alternatives considered

- **Order from cart:** rejected — no frozen price/stock/delivery, no reservation boundary.
- **Mutable quote row:** rejected — silent history rewrite.
- **Reserve in cart:** rejected — abandoned carts freeze sellable stock (ADR 0012).
- **Client totals with server check:** rejected — clients must not be in the trust path.
