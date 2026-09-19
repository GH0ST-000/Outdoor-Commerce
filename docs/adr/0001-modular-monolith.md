# ADR 0001: Modular Monolith

- **Status:** Accepted
- **Date:** 2026-09-19
- **Deciders:** Engineering (Day 2 architecture foundations)

## Context

Outdoor Commerce will grow into ecommerce, inventory, payments, hunting legality, geography, and recommendations. Day 1 established a single Laravel API and Next.js storefront. We need clear module boundaries before business features arrive, without premature microservice complexity.

## Decision

The backend begins as a **modular monolith**:

- One deployable Laravel application.
- Business capabilities live in explicit domain modules under `App\Domains\*`.
- Each module owns its rules and internal implementation.
- Cross-module access is allowed only through approved public contracts, Actions, Queries, DTOs, or events.
- Laravel remains the authority for business rules.
- No in-process network calls between modules and no microservices in this phase.

## Reasons

- Faster delivery with one codebase, one database transaction boundary, and one deployment unit.
- Clear ownership prevents “god controllers” and accidental coupling as features land.
- Modules remain extractable later if scale or team boundaries justify it.
- Avoids distributed-system costs (network latency, eventual consistency, duplicate auth) before they are needed.

## Consequences

- Engineers must learn and follow module dependency rules, enforced by architecture tests.
- Public APIs of modules must be intentional; internal classes are not fair game.
- Shared code must stay small and technical; it must not become a dumping ground.
- Documentation and tests become part of the definition of done for structural changes.

## Advantages

- Simple local development and operations (Docker Compose, one API).
- Strong consistency for orders, inventory, and payments within one database.
- Refactors stay local when boundaries are respected.
- Incremental path to extraction without rewriting the product on Day 2.

## Trade-offs

- Discipline is required; boundaries can erode without automated checks.
- A poorly designed module can still grow too large inside the monolith.
- Extraction later still costs work; modularity reduces but does not eliminate that cost.
- Some duplication may be preferred over leaking another module’s internals.

## Rules for cross-module communication

### Allowed

- Interfaces and types under `DomainName/Contracts/`
- Public Actions and Queries documented as cross-module entry points
- Domain/application events (past tense) carrying identifiers and safe payload data
- DTOs that are part of a module’s public contract (`*Data`)
- Shared value objects from `App\Domains\Shared`

### Forbidden

- Importing another module’s internal Services, Models, or Support classes for arbitrary use
- Writing directly to another module’s tables
- Calling another module’s HTTP controllers
- Sharing mutable global state
- Copying another module’s business rules
- Using events when an immediate synchronous result is required
- Circular dependencies between modules

## Conditions that could justify extracting a module later

Extract a module only when at least one of the following is sustained and measurable:

1. Independent scaling or failure isolation is required (for example payments provider load).
2. A separate team needs an independent release cadence with clear ownership.
3. Regulatory or security isolation requires a separate trust boundary.
4. The module’s data and traffic profile dominate the monolith and harm other domains.

Until then, keep the modular monolith and invest in clearer contracts inside one deployable unit.
