# Checkout module

## Responsibility

Server-authoritative checkout sessions, immutable quote revisions, configured fulfillment, and short-lived inventory reservations. A quote is not an order and is not a payment.

## Data owned

Checkout sessions, checkout-lifecycle addresses, quotes, quote lines, quote adjustments, fulfillment methods, pickup locations, delivery zones, delivery rate rules, checkout restriction rules, checkout idempotency records.

## Public contracts

- HTTP: `App\Http\Controllers\Api\V1\Checkout\PublicCheckoutController`
- Actions under `Actions/`
- `Contracts\FulfillmentQuoteProvider`
- Day 19 consumes a stored **active** `CheckoutQuote` (status `active`, `expires_at` in the future, matching session/fingerprint). Reservations transfer to the pending-payment order; they are not committed. Do not trust browser-copied totals.

## Events this module may publish

`CheckoutSessionCreated`, `CheckoutContactUpdated`, `CheckoutAddressUpdated`, `CheckoutFulfillmentSelected`, `CheckoutQuoteCreated`, `CheckoutQuoteSuperseded`, `CheckoutQuoteExpired`, `CheckoutSessionCancelled`, `CheckoutSessionExpired`, `InventoryReservedForQuote`, `QuoteReservationReleased`.

## May depend on

Shared; Cart (`CartOwnerResolver`, models, DTOs); Pricing (`PublicCatalogPricing`); Inventory (`CheckoutInventoryService`, reservation models/DTOs); Catalog models; Identity address book only when the shopper opts in. Public media URLs are hydrated in the HTTP layer.

## Explicitly outside this module

Order creation, payments, carrier APIs, geocoding, legal hunting claims, admin fulfillment UI (seed/config in Day 18).

## Structure

See `docs/checkout.md` and `docs/adr/0013-immutable-checkout-quotes.md`.
