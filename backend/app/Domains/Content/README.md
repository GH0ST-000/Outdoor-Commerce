# Content module

## Responsibility

Editorial and CMS-like content blocks for the storefront.

## Data owned

Pages, articles, banners, editorial media references.

## Public contracts

Content retrieval queries for storefront.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

ContentPublished (example for later).

## May depend on

Shared; Identity for authors.

## Explicitly outside this module

Canonical product data and inventory.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
