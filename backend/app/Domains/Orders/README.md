# Orders module

## Responsibility

Order lifecycle and immutable snapshots of what was purchased.

## Data owned

Orders, order items, status transitions.

## Public contracts

Order creation/query contracts for Payments/Shipping/Notifications.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

OrderCreated, OrderCancelled, OrderFulfilled (examples for later).

## May depend on

Shared; Identity; Catalog/Inventory/Pricing snapshots via contracts.

## Explicitly outside this module

Payment-provider implementation and cart mutation.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
