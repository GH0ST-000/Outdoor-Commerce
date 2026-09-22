# Cart module

## Responsibility

Server-authoritative shopper carts before checkout. Guest and authenticated ownership, line mutations, current-price recalculation, availability checks without reservations, guest-to-user merge, expiry.

## Data owned

`carts`, `cart_items`, `cart_idempotency_records` in MySQL.

## Public contracts

HTTP Actions: `GetCartAction`, `AddCartItemAction`, `UpdateCartItemAction`, `RemoveCartItemAction`, `ClearCartAction`, `MergeGuestCartAction`, `ExpireCartsAction`. Other modules should not import Cart services; Identity merge is invoked from HTTP auth controllers.

## Events this module may publish

`CartCreated`, `CartItemAdded`, `CartItemQuantityChanged`, `CartItemRemoved`, `CartCleared`, `CartMerged`, `CartExpired`, `CartPriceChanged`, `CartAvailabilityChanged`. Payloads use public IDs only — never guest tokens.

## May depend on

Shared; Catalog (`Contracts`, `Enums`, `Models`); Pricing `PublicCatalogPricing`; Inventory `PublicInventoryAvailability`.

## Explicitly outside this module

Orders, payments, shipping quotes, inventory reservations, coupon administration.

## Structure

See `docs/cart.md` and ADR 0012.
