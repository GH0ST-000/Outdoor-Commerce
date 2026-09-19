# Cart module

## Responsibility

Shopper cart composition before checkout.

## Data owned

Cart lines tied to a shopper/session.

## Public contracts

Cart read/write contracts for storefront orchestration.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

CartUpdated (example for later).

## May depend on

Shared; Catalog/Pricing/Inventory via public contracts only.

## Explicitly outside this module

Final order records, payment intents.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
