# Orders module

## Responsibility

Order lifecycle and immutable snapshots of what was purchased. Day 19 creates pending-payment orders by consuming a stored checkout quote exactly once.

## Data owned

Orders, order items, order adjustments, order status history, order idempotency records, guest order access hashes.

## Public contracts

- HTTP: `App\Http\Controllers\Api\V1\Orders\PublicOrderController`
- Actions: `CreateOrderFromQuoteAction`, `GetOrderAction`, `CancelOrderAction`, `ExpireUnpaidOrdersAction`, `ApplyOrderPaymentStartedAction`, `ApplyOrderPaymentSucceededAction`, `ApplyOrderPaymentReturnedToPendingAction`, `ApplyOrderManualReviewAction`, `AssertOrderAccessAction`, `ExpireOrderIfDueAction`
- Events: `OrderCreated`, `OrderPendingPayment`, `OrderCancelled`, `OrderExpired`, `OrderConfirmed`, `OrderReservationTransferred`, `OrderReservationReleased`

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

`OrderCreated`, `OrderPendingPayment`, `OrderCancelled`, `OrderExpired`, `OrderConfirmed`, `OrderReservationTransferred`, `OrderReservationReleased`.

## May depend on

Shared; Checkout (models, enums, DTOs, Actions); Cart (models, enums, Actions, `CartOwnerResolver`); Inventory (`CheckoutInventoryService`, reservation models); Catalog (`SnapshotPublicProductMediaAction`, product/variant models); Operations audit actions; Identity user model.

## Explicitly outside this module

Payment-provider implementation, refunds, shipments, customer emails, admin order UI.

## Structure

See `docs/orders.md` and `docs/adr/0014-atomic-order-creation.md`.
