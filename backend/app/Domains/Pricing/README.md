# Pricing module

## Responsibility

List/sale prices, promotions, and coupon eligibility.

## Data owned

Price lists, promotions, coupon definitions.

## Public contracts

Price resolution contracts used by Cart/Checkout.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

PriceUpdated, PromotionActivated (examples for later).

## May depend on

Shared; Catalog identifiers via contracts.

## Explicitly outside this module

Inventory quantities, order persistence.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
