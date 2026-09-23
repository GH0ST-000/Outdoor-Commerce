# ADR 0014: Atomic order creation from immutable checkout quotes

## Status

Accepted — Day 19

## Context

Day 18 freezes a payable total and holds stock on an immutable quote. Creating an order from a cart, from browser-copied totals, or from a mutable quote row would let prices, promotions, fulfillment, or inventory change between review and persistence. Duplicate submits and lost responses are normal on the public internet. Guest shoppers must be able to refresh a confirmation page without putting a secret in the URL.

Committing inventory at order creation would mark stock sold before payment. Releasing quote holds at create would put reserved units back on sale during the payment window.

## Decision

1. **Orders come only from a stored active quote.** Laravel loads the quote from MySQL. The client sends session public id, quote public id, and checkout version. Money, names, and inventory values from the client are prohibited.

2. **`checkout_quote_id` is unique on `orders`.** Combined with row locks and required `Idempotency-Key`, this yields effectively-once creation. Duplicate keys replay. Different keys for the same quote return the existing order.

3. **Quote consumption is atomic with order insert.** Quote `consumed`, session `converted`, cart `converted`, and reservation reassignment happen in one transaction. Failure rolls everything back.

4. **Order rows are commercial snapshots.** Item names, SKUs, attributes, bounded media URLs, contact, address (encrypted), fulfillment, and adjustments are copied from the quote. Later catalog, price, or profile edits do not rewrite history.

5. **Reservations transfer, they are not committed.** `reference_type` becomes `order` and `expires_at` becomes the pending-payment TTL. Day 20 commits after verified payment. Cancel and unpaid expiry release active holds only.

6. **Order, payment, and fulfillment statuses are separate.** Day 19 initializes `pending_payment` / `unpaid` / `unfulfilled`. A central state machine records every order-status change. There is no public status PATCH.

7. **Guest order access uses an HttpOnly cookie.** A hash of a random token is stored. Order number and public id are not credentials.

8. **Day 20 attaches payment attempts to the order.** It reads the stored grand total and currency. It does not recreate the order from the cart.

## Consequences

- Shoppers must have an unexpired, current, reserved quote to place an order.
- Unpaid orders expire; stock returns to sellable availability.
- Confirmation copy must say the order was created and payment is still pending.
- Payment providers, emails, refunds, and admin order UI remain later days.

## Alternatives considered

- **Order from cart:** rejected — no frozen price, stock, or delivery (ADR 0013).
- **Commit stock at create:** rejected — unpaid orders would consume `on_hand` before payment.
- **Release quote holds at create:** rejected — competing checkouts could sell the same units during payment.
- **Order number as guest credential:** rejected — enumerable and leaked in support conversations.
- **Client totals with server check:** rejected — clients stay out of the trust path.
