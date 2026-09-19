# Checkout module

## Responsibility

Orchestration from cart toward a placed order.

## Data owned

Checkout session state only (not long-term orders).

## Public contracts

Start/complete checkout orchestration actions.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

CheckoutStarted, CheckoutCompleted (examples for later).

## May depend on

Shared; Cart, Pricing, Inventory, Orders, Shipping via contracts/actions.

## Explicitly outside this module

Payment-provider SDKs and reconciliation details.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
