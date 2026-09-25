# Fulfillment and shipments (Day 22)

Laravel is the authority for post-payment fulfillment. Checkout delivery pricing is unchanged. Inventory is **not** deducted again at dispatch — Day 20 already committed stock after verified payment.

See [ADR 0017](adr/0017-carrier-neutral-fulfillment.md).

## Concepts

| Concept | Owner | Notes |
| --- | --- | --- |
| Order `fulfillment_status` | Aggregate | Derived from shipments: `unfulfilled`, `processing`, `partially_fulfilled`, `fulfilled`, `cancelled`, `exception` |
| Shipment | Physical batch | Delivery or store pickup |
| Shipment item | Allocation | Quantity of one order item. May split across shipments |
| Shipment event | Timeline | Append-only. Customer-safe vs internal |
| Carrier provider | Adapter | Day 22: `manual` only |

Order, payment, fulfillment, and shipment statuses stay separate. The browser cannot `PATCH` a final status.

## Eligibility

Ordinary shipment creation requires: order exists, **paid**, **confirmed**, not cancelled/expired, remaining fulfillable quantity, committed inventory reservations, compatible fulfillment type, `fulfillment.manage`.

Unpaid orders cannot enter fulfillment. Cash on delivery is a future policy.

## Allocation

Allocated quantity across non-cancelled shipments cannot exceed ordered quantity. Cancelled **draft/preparing** allocation returns to the pool. Shipped/collected/delivered quantity does not return without a future returns workflow.

Warehouse comes from Day 10 committed reservations (`reference_type=order`). Admins may pass an eligible `warehouse_code` (never a public warehouse id). Customers never see warehouse codes or ids.

## Delivery state machine

`draft → preparing → ready_for_dispatch → shipped → in_transit → out_for_delivery → delivered`

Also: shipped may go directly to delivered; out_for_delivery may go to `delivery_attempt_failed`; exception can resume to in_transit / out_for_delivery / delivered. Delivered is terminal.

Pick and pack must complete before ready-for-dispatch. Dispatch copies packed quantity to shipped quantity.

## Store pickup state machine

`draft → preparing → ready_for_pickup → collected`

Pickup location is the checkout/order snapshot. It cannot be silently switched. Collected is terminal. Ready-for-pickup and collected are explicit admin actions. Staff identity is not public.

Pickup ready copies packed quantity to shipped quantity internally so delivered/collected quantity never exceeds shipped quantity.

## Manual provider

Code `manual`. It does **not** synchronize with a courier. Operators enter an optional tracking number and an allowlisted HTTPS tracking URL. `javascript:` and `data:` URLs are rejected. Tracking numbers are identifiers, not credentials.

## Customer tracking

`GET /api/v1/orders/{orderPublicId}/fulfillment` (same ownership as the order: Sanctum or guest order cookie). Also nested as `fulfillment_progress` on `GET /api/v1/orders/{orderPublicId}`.

Public payload: shipment number, type, customer-safe timeline, items, tracking number/URL, pickup name/address/instructions/hours when present, estimated range only when stored, remaining quantities. No warehouse ids, internal notes, admin identity, or provider payloads.

`Cache-Control: private, no-store`.

## Admin API

Protected under `/api/v1/admin/...` with `fulfillment.view` / `fulfillment.manage` (also granted to `order-manager`). Explicit action routes, required `expected_version`, `Idempotency-Key`. Stale version → HTTP 409.

## Webhooks and reconciliation

Inbox table exists for future carriers. `POST /api/v1/shipments/webhooks/{provider}` rejects `manual` and unknown providers. Unknown carrier statuses must never become `delivered`.

```bash
php artisan shipments:reconcile
php artisan shipments:reconcile --shipment={publicId}
php artisan shipments:detect-stale
```

Reconcile skips the manual provider. Stale detection reports only; it never marks delivered or cancelled.

## Cancellation

Customer simple cancel remains pending-unpaid only. `can_cancel` and `cancellation_reason_code` come from Laravel. After fulfillment starts, simple cancel is blocked. No automatic refund.

## Permissions and audit

Every mutation is audited (`shipment.*` events). Domain events dispatch after commit for later notifications.

## Error codes

`FULFILLMENT_ORDER_NOT_ELIGIBLE`, `FULFILLMENT_ORDER_NOT_PAID`, `FULFILLMENT_NOTHING_REMAINING`, `FULFILLMENT_QUANTITY_EXCEEDED`, `FULFILLMENT_WAREHOUSE_INVALID`, `FULFILLMENT_TYPE_INVALID`, `SHIPMENT_NOT_FOUND`, `SHIPMENT_VERSION_CONFLICT`, `SHIPMENT_INVALID_TRANSITION`, `SHIPMENT_QUANTITY_INVALID`, `SHIPMENT_ALREADY_DISPATCHED`, `SHIPMENT_ALREADY_DELIVERED`, `SHIPMENT_ALREADY_COLLECTED`, `SHIPMENT_CANCELLATION_NOT_ALLOWED`, `SHIPMENT_PROVIDER_UNAVAILABLE`, `SHIPMENT_TRACKING_URL_INVALID`, `SHIPMENT_IDEMPOTENCY_CONFLICT`, `PICKUP_LOCATION_INVALID`.

## Local testing

1. Pay a test order (test payment provider).
2. As `order-manager`, `POST /api/v1/admin/orders/{id}/shipments` with `provider_code=manual`.
3. Pick, pack, mark ready for pickup or dispatch through the action endpoints.
4. Refresh the order confirmation page; only customer-safe events appear.
5. `php artisan shipments:detect-stale` reports without mutating.

## Future courier adapter

Implement `ShipmentProvider`, register in `config/shipping.php`, map statuses explicitly, verify webhooks before any state change, call the provider **after** the DB transaction when creating labels.

## Known limitations

No real courier, no GPS, no emails/SMS, no refunds/returns, no admin order dashboard (Day 23), no signed public tracking link.
