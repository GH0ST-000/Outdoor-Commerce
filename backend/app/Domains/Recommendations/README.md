# Recommendations module

## Responsibility

Deterministic contextual ranking of products for display.

## Data owned

Ranking inputs/outputs and recommendation traces.

## Public contracts

Recommend products for a given outdoor context contract.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

RecommendationsGenerated (example for later).

## May depend on

Shared; Catalog and optional Hunting/Geography context via contracts.

## Explicitly outside this module

Legal allow/deny decisions. Recommendations never decide legality.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
