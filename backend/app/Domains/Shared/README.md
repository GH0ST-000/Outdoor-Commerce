# Shared module

## Responsibility

Cross-cutting technical primitives shared by all modules.

## Data owned

No business aggregates. May hold correlation ID and future shared value objects.

## Public contracts

Public support types such as CorrelationId; keep the surface small.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

None expected for Day 2.

## May depend on

Laravel framework primitives only. Must not depend on any business module.

## Explicitly outside this module

Product, order, payment, hunting, or any domain workflow logic.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
