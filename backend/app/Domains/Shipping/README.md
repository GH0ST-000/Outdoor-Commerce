# Shipping module

## Responsibility

Shipments, rate selection, and fulfillment addressing.

## Data owned

Shipments and shipping addresses/labels metadata.

## Public contracts

Shipment creation/query contracts.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

ShipmentCreated, ShipmentDispatched (examples for later).

## May depend on

Shared; Orders via contracts; Geography for zones when needed.

## Explicitly outside this module

Inventory reservation algorithms and payment capture.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
