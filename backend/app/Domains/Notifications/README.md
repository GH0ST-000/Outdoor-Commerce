# Notifications module

## Responsibility

Outbound notification orchestration (email, SMS, push).

## Data owned

Notification dispatches and delivery attempts.

## Public contracts

Notify user/order contracts for other modules after commit.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

NotificationQueued, NotificationSent (examples for later).

## May depend on

Shared; Identity/Orders identifiers via contracts.

## Explicitly outside this module

Deciding whether an order or payment exists.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
