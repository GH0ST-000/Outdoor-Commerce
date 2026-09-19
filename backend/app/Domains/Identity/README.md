# Identity module

## Responsibility

Authentication, accounts, credentials, sessions, and authorization identities.

## Data owned

Users, credentials, roles/permissions assignments.

## Public contracts

Contracts for resolving the current user / permission checks used by other modules.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

UserRegistered, UserDeactivated (examples for later).

## May depend on

Shared.

## Explicitly outside this module

Catalog content, inventory, payments, hunting legality.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
