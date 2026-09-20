# Inventory module

## Responsibility

Warehouses, variant-level stock balances, immutable ledger, and reservations.

## Day 10 public surface

- Models: `Warehouse`, `InventoryBalance`, `InventoryOperation`, `InventoryLedgerEntry`, `InventoryReservation`
- Actions: receive, adjust, reconcile stock count, transfer, reserve/release/commit/cancel/expire, warehouse lifecycle, settings
- Services: balance locking, ledger writes, idempotency, allocation strategy, low-stock crossing events
- Contracts: `InventoryAllocationStrategy`, `CheckoutInventoryService` (for Cart/Checkout/Orders)
- Commands: `inventory:expire-reservations`, `inventory:verify-balances`
- Admin APIs under `/api/v1/admin/warehouses` and `/api/v1/admin/inventory`

Docs: [docs/inventory-ledger.md](../../../../docs/inventory-ledger.md), [ADR 0005](../../../../docs/adr/0005-immutable-inventory-ledger.md).

## Quantity model

```text
on_hand, reserved, safety_stock, reorder_point  (persisted)
unreserved = on_hand - reserved
available_to_sell = max(0, on_hand - reserved - safety_stock)
```

Stock is never stored on `products` or `product_variants`.

## Reservation events

Transition history is recorded in the immutable Operations audit log (`inventory.reservation_*`). A separate `inventory_reservation_events` table was not added to avoid duplicating that trail.

## May depend on

Shared; Catalog identifiers (`product_variants.id`); Operations audit; Identity permission names in policies.

## Explicitly outside this module

Pricing, cart, checkout, orders, purchase orders, notifications.
