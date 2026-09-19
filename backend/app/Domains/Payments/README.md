# Payments module

## Responsibility

Payment intents, provider callbacks, and reconciliation.

## Data owned

Payment intents, captures, refunds, provider references.

## Public contracts

Initiate/confirm payment contracts for Checkout/Orders.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

PaymentConfirmed, PaymentFailed (examples for later).

## May depend on

Shared; Orders via contracts.

## Explicitly outside this module

Order line composition and inventory ledgers.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
