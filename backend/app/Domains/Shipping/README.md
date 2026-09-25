# Shipping module

## Responsibility

Carrier-neutral shipments, store pickup, tracking timelines, and aggregate order fulfillment status. Day 22 ships a production-usable **manual** provider. No courier API is integrated.

## Data owned

Shipments, shipment items, append-only shipment events, shipment webhook inbox (future carriers), shipment idempotency records. Aggregate `orders.fulfillment_status` is written from this module after shipment mutations.

## Public contracts

- HTTP: `PublicOrderFulfillmentController`, `AdminFulfillmentController`, `ShipmentWebhookController`
- Actions: `CreateShipmentAction`, `TransitionShipmentAction`, `GetOrderFulfillmentAction`, `GetAdminFulfillmentAction`, `ReceiveShipmentWebhookAction`, `ReconcileShipmentsAction`, `DetectStaleShipmentsAction`
- Contract: `ShipmentProvider`
- Events: `FulfillmentStarted`, `ShipmentCreated`, `ShipmentPreparationStarted`, `ShipmentItemsPicked`, `ShipmentItemsPacked`, `ShipmentReadyForDispatch`, `ShipmentDispatched`, `ShipmentInTransit`, `ShipmentOutForDelivery`, `ShipmentDeliveryAttemptFailed`, `ShipmentDelivered`, `ShipmentExceptionRecorded`, `ShipmentCancelled`, `PickupReady`, `PickupCollected`, `OrderPartiallyFulfilled`, `OrderFulfilled`

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

Listed above. No customer email or SMS in Day 22.

## May depend on

Shared; Orders (Actions, Models, Enums, DTOs); Inventory models for warehouse provenance of committed reservations; Operations audit actions; Checkout pickup snapshots already stored on the order.

## Explicitly outside this module

Inventory deduction (already committed after verified payment), payment capture, checkout rate shopping, refunds, returns, a real courier SDK.

## Structure

See `docs/fulfillment.md` and `docs/adr/0017-carrier-neutral-fulfillment.md`.
