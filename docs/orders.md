# Orders (Day 19)

Laravel is the authority for order creation. An order can only be created by consuming a stored **active** Day 18 checkout quote. The storefront never submits prices, discounts, delivery charges, taxes, totals, product names, or reservation ids.

See [ADR 0014](adr/0014-atomic-order-creation.md).

## Statuses

Three independent fields:

| Field | Day 19 initial value | Notes |
| --- | --- | --- |
| `status` | `pending_payment` | Order lifecycle. Central `OrderStateMachine`. |
| `payment_status` | `unpaid` | Payment lifecycle. `OrderPaymentStatusService`. Day 20 moves this. |
| `fulfillment_status` | `unfulfilled` | Aggregate from Day 22 shipments: `unfulfilled`, `processing`, `partially_fulfilled`, `fulfilled`, `cancelled`, `exception`. |

Do not treat pending-payment as paid or confirmed.

Valid Day 19 / Day 20 order-status transitions:

`pending_payment` → `payment_processing` | `cancelled` | `expired`  
`payment_processing` → `confirmed` | `pending_payment` | `cancelled`  
`manual_review` → `confirmed` | `cancelled`

History is append-only (`order_status_histories`). There is no public `PATCH status`.

## Quote consumption

`CreateOrderFromQuote` (one MySQL transaction):

1. Lock checkout session then quote (deterministic ids).
2. If an order already exists for `checkout_quote_id` and the caller owns it, return it.
3. Validate ownership, session, quote `active` + current + unexpired, version, HMAC fingerprint, financial invariant, restrictions, and active reservations.
4. Generate `ORD-YYYYMMDD-XXXXXX` (Crockford-like alphabet, collision retry).
5. Insert immutable order, items, adjustments, contact/address snapshots, fulfillment snapshot.
6. Reassign quote reservations to `reference_type=order` with a pending-payment TTL (default 20 minutes). Do **not** commit stock.
7. Mark quote `consumed`, session `converted`, cart `converted`.
8. Issue an HttpOnly guest order cookie (hash stored; raw token never in JSON).
9. Commit, then dispatch `OrderCreated`, `OrderPendingPayment`, `OrderReservationTransferred`.

Unique `checkout_quote_id` is the last-line duplicate protection. `Idempotency-Key` is required. Same key + payload replays the stored body. Different keys for the same quote still yield one order.

Fingerprint: HMAC of stored commercial fields (cart version, contact/fulfillment/address, line prices/SKU, totals). Consume recomputes it; it does not reprice.

Financial invariant:

`grand_total = items_subtotal - discount_total + delivery_total + tax_total + rounding adjustments`

## Reservations

Quote hold → order payment hold. `on_hand` is unchanged until Day 20 payment success commits reservations. Cancel and unpaid expiry release **active** order reservations only. Committed/paid holds are never released by `orders:expire-unpaid`.

`inventory:expire-reservations` still expires rows by `expires_at`. Order create extends `expires_at` to the payment window so the inventory job does not drop a live unpaid order early.

## Guest access

`public_id` is routing. `order_number` is for customers. Neither is a credential.

Guest cookie `outdoor_guest_order`: HttpOnly, Secure in production, SameSite=lax, HMAC of the raw token stored as `access_token_hash`. Not placed in URLs, logs, or JSON.

Authenticated orders require Sanctum identity matching `user_id`. Another user gets generic `ORDER_NOT_FOUND`.

## API

All responses `{ data: … }` with `Cache-Control: private, no-store`.

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/api/v1/orders` | Body: `checkout_session_id`, `quote_id`, `checkout_version`. Header: `Idempotency-Key`. First create `201`. |
| GET | `/api/v1/orders/{orderPublicId}` | Confirmation refresh. Request-time expiry. Includes `fulfillment_progress`. |
| GET | `/api/v1/orders/{orderPublicId}/fulfillment` | Customer-safe shipments and timeline. Same ownership as the order. |
| POST | `/api/v1/orders/{orderPublicId}/cancel` | Pending unpaid only. Blocked after fulfillment starts. Idempotent. |

Client money/status fields are prohibited.

## Commands

```bash
php artisan orders:expire-unpaid
php artisan checkout:expire-quotes
php artisan inventory:expire-reservations
```

Scheduled every minute with quote and inventory expiry.

## Day 20

Payment lives in the Payments module. See [payments.md](payments.md) and [ADR 0015](adr/0015-provider-agnostic-payment-core.md).

Order GET includes `can_pay`, `can_retry_payment`, and `current_payment_attempt`. Verified payment success confirms the order and commits reservations. Failure does not immediately release a still-valid hold. Late provider success after expiration enters `manual_review`.

## Query notes

Create: lock session → quote → reservations by id. Batch-insert items and adjustments. No Meilisearch, no image processing, no HTTP inside the transaction.

GET: one order by `public_id` + items/adjustments. Media URLs come from the stored snapshot.
