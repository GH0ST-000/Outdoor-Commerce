# ADR 0017 — Carrier-neutral fulfillment with shipment-level state machines

## Status

Accepted (Day 22).

## Context

Paid, confirmed orders still showed `fulfillment_status = unfulfilled`. Checkout already stored delivery vs store-pickup snapshots. Inventory was committed after verified payment. The product needs warehouse pick/pack, partial shipments, pickup, and customer tracking without pretending a courier API exists.

## Decision

1. **Fulfillment is separate from payment and order status.** Aggregate order fulfillment is derived from shipments. The storefront cannot assign it.

2. **One order may have multiple shipments**, including split lines. Allocation is quantity-based, locked in deterministic order-item order, and cannot exceed the immutable ordered quantity.

3. **Manual fulfillment works without a carrier API.** Provider code `manual` records operator actions honestly (`provider.manual = true`). It does not fetch courier status.

4. **Future couriers implement `ShipmentProvider`.** Create, fetch, cancel, and verified webhooks stay behind that contract. Provider SDK arrays do not leak into HTTP or React.

5. **Customer tracking is derived from safe shipment events.** Internal notes, warehouse ids, staff identity, and raw provider payloads are omitted. Tracking numbers are untrusted identifiers, not credentials. Guest order cookies / authenticated ownership remain the access control.

6. **Inventory is not deducted at dispatch.** Day 20 already committed the ledger. Shipping only allocates ordered quantities.

7. **Browser redirects and tracking pages are non-authoritative.** Closing a carrier tab does not change shipment state.

## Consequences

Day 23 can add customer order history and an admin order console on these APIs. Adding a Georgian courier later is a new adapter plus config, not a rewrite of orders or inventory.
