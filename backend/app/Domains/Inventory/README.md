# Inventory module

## Responsibility

Stock quantities, availability, and reservations.

## Data owned

Stock ledger and reservation records.

## Public contracts

Reservation and availability contracts for Checkout/Orders.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

InventoryReserved, InventoryReleased (examples for later).

## May depend on

Shared; Catalog identifiers via contracts.

## Explicitly outside this module

Product descriptions, pricing rules, payment capture.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
