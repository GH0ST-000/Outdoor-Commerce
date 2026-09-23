# Checkout sessions and quotes (Day 18)

Laravel is the authority for checkout ownership, contact and address validation, fulfillment eligibility, delivery rates, current prices, promotions, quote totals, and inventory reservations. MySQL is the source of truth. The storefront never submits trusted prices or totals.

See [ADR 0013](adr/0013-immutable-checkout-quotes.md).

A **checkout session** is a mutable workflow. A **quote** is an immutable calculation snapshot. A **reservation** is a short-lived hold created only when a quote succeeds. None of these is an order.

## Session lifecycle

`draft` → `ready` (contact complete) → `quoted` → `converted` (Day 19)  
Also: `expired`, `cancelled`.

- `POST /api/v1/checkout/sessions` resolves the caller’s active cart (Day 17 ownership). Empty carts are rejected. Opening checkout does **not** reserve stock. A compatible active session for the same cart/owner is reused.
- Session `version` increments on every mutation. Quote creation also increments it.
- Guest ownership reuses the Day 17 HttpOnly cart cookie. Only HMAC of the token is stored (`guest_token_hash`). The checkout public UUID is not a credential.
- Authenticated ownership is Sanctum identity. Client `user_id` / `cart_id` are prohibited.
- Converted and cancelled sessions cannot be mutated. Expired sessions cannot create quotes.
- Defaults: session TTL **24 hours** (`CHECKOUT_SESSION_TTL_HOURS`). Max **3** active sessions per owner.

## Quote revisions

Quotes are never recalculated in place. New inputs or an explicit refresh insert a new `revision`. The previous **active** quote becomes `superseded` and its reservations are released inside the same transaction **before** new reservations are created so a shopper can re-quote their own quantity.

Statuses: `active`, `superseded`, `expired`, `consumed`, `cancelled`.

Day 19 may consume only `active` quotes whose `expires_at` is still in the future. Default quote TTL **15 minutes** (`CHECKOUT_QUOTE_TTL_MINUTES`). Quote refresh count is capped (`CHECKOUT_MAX_QUOTE_REFRESHES`, default 8). GET/refresh does not extend TTL.

Fingerprint: HMAC-SHA256 of canonical JSON (cart version, normalized contact/fulfillment/address, price list, line prices and signatures, delivery amount). The API returns the first 32 hex characters. Day 19 must re-read the stored hash, not the public fragment from the browser.

Tax: `price_includes_tax` is **true** by default (`CHECKOUT_PRICE_INCLUDES_TAX`). `tax_total_minor` is **0**. There is no VAT engine yet; Georgian catalog prices are treated as tax-inclusive. Do not add a second tax.

Money: integer minor units only. Mixed currencies are rejected. Catalog/cart currency must match `CHECKOUT_CURRENCY` (GEL).

## Fulfillment

Configured methods: `store_pickup`, `local_delivery`, `courier_delivery`. Labels are translation JSON. Fees live in Laravel (`FulfillmentQuoteProvider`). React must not compute eligibility or fees.

Rate matching: country → optional region → optional city → optional postal regex. Lowest numeric `priority` wins. Two matches at the same priority fail closed (`CHECKOUT_DELIVERY_ZONE_UNAVAILABLE`). Free delivery applies only when `free_above_minor` on the winning rule (or pickup method) is met. No match is never silently zero.

Store pickup does not require a street. Contact remains required. Pickup uses the default warehouse allocation (Day 10); it does not pin a store-specific warehouse.

Admin CRUD UI is deferred. Local/testing seed: `FulfillmentSeeder`.

## Inventory

Cart still does **not** reserve. Quote creation calls `CheckoutInventoryService::reserve()` per line, variant ids locked in ascending order, `reference_type=checkout_quote`. Failure rolls back the transaction (no partial holds). Reservations expire with the quote. `inventory:expire-reservations` remains the ledger expire path; `checkout:expire-quotes` marks quotes expired and releases via the same inventory actions.

Abuse limits: quote TTL, session cap, refresh cap, line quantity cap (same as cart), quote rate limit (`checkout.quote`, default 8/min), idempotency.

## API

All responses `{ data: { checkout_session, quote } }` with `Cache-Control: private, no-store`.

| Method | Path |
| --- | --- |
| POST | `/api/v1/checkout/sessions` |
| GET | `/api/v1/checkout/sessions/{id}` |
| PATCH | `.../contact` |
| PATCH | `.../address` |
| PATCH | `.../fulfillment` |
| POST | `.../quote` |
| DELETE | `.../{id}` |

Mutations require `Idempotency-Key`. Quote requires `checkout_version` and `cart_version`. Stale versions → HTTP 409 `CHECKOUT_VERSION_CONFLICT`.

Phone storage is E.164 (`+995…` for Georgian mobiles). Guest addresses stay on `checkout_addresses` unless an authenticated shopper sets `save_to_account`.

Restrictions: `checkout_restriction_rules` only. Category names never imply legal limits. No legal claims are seeded.

## Commands

```bash
php artisan checkout:expire-quotes
php artisan checkout:expire-sessions
php artisan inventory:expire-reservations
```

Scheduled: quotes every minute, sessions every five minutes, inventory reservations every minute.

## Query notes

Session GET: one session by public id + ownership, cart lines batched, pricing `quoteVariants`, fulfillment methods + rates, current quote lines. Quote create: lock session then cart, batch cart lines/products/variants/prices/availability/restrictions, then inventory locks per variant. Avoid Meilisearch and original images.

## Day 19 contract

Consume the **stored** active quote: verify ownership, `status=active`, `expires_at`, current revision, fingerprint, financial invariant, and active reservations. Create a pending-payment order and **transfer** reservations to the order (`reference_type=order`) with a payment-window TTL. Do not commit stock and do not accept client totals. See [orders.md](orders.md) and [ADR 0014](adr/0014-atomic-order-creation.md).
