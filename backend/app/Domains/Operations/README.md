# Operations module

## Responsibility

Admin and operational tooling, audits, and support workflows.

## Data owned

Ops audit trails and support case metadata.

## Public contracts

Internal ops actions; not a public storefront API.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

AuditRecorded (example for later).

## May depend on

Shared; may call other modules only through public contracts.

## Explicitly outside this module

Customer cart/checkout UX.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
