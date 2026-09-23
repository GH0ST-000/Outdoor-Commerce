# Architecture

## System context

Outdoor Commerce is an outdoor retail platform for hunting, fishing, and related equipment. The backend is a **modular monolith**: one Laravel deployable with explicit domain modules under `App\Domains`. The Next.js storefront consumes the API and never independently calculates final prices, stock, legal status, or recommendations.

See also:

- [ADR 0001: Modular monolith](adr/0001-modular-monolith.md)
- [API conventions](api-conventions.md)
- [Catalog media (Day 9)](catalog-media.md)
- [Inventory ledger (Day 10)](inventory-ledger.md)
- [Public catalog API (Day 12)](public-catalog.md)
- [Storefront design system (Day 13)](design-system.md)
- [Storefront homepage and listing (Day 14)](storefront.md)
- [Product detail (Day 15)](product-detail.md)
- [Catalog search (Day 16)](search.md)
- [Cart (Day 17)](cart.md)
- [ADR 0007: Rebuildable public catalog projections](adr/0007-rebuildable-public-catalog-projections.md)
- [ADR 0008: Semantic design tokens and storefront component boundaries](adr/0008-semantic-design-tokens.md)
- [ADR 0009: URL-driven storefront filters and server-first category rendering](adr/0009-url-driven-storefront-filters.md)
- [ADR 0010: Client-side variant resolution from an authoritative public variant matrix](adr/0010-client-side-variant-resolution.md)
- [ADR 0011: Meilisearch as a derived variant-aware catalog search model](adr/0011-meilisearch-derived-search.md)
- [ADR 0012: Persistent server-authoritative cart with secure guest identity](adr/0012-persistent-server-authoritative-cart.md)
- [Checkout (Day 18)](checkout.md)
- [Orders (Day 19)](orders.md)
- [Payments (Day 20)](payments.md)
- [ADR 0013: Immutable checkout quotes with short-lived inventory reservations](adr/0013-immutable-checkout-quotes.md)
- [ADR 0014: Atomic order creation from immutable checkout quotes](adr/0014-atomic-order-creation.md)
- [ADR 0015: Provider-agnostic payment core with verified webhook authority](adr/0015-provider-agnostic-payment-core.md)
- Per-module notes under `backend/app/Domains/*/README.md`

## Modular monolith definition

- One Laravel application process and deployment unit.
- Business capabilities are separated into domain modules.
- Each module owns its rules and internals.
- Cross-module access only via approved contracts, Actions, Queries, DTOs, or events.
- Designed so a module could be extracted later if real scaling or organizational needs justify it.
- No microservices and no network calls between modules in this phase.

## Domain module list

| Module | Owns | Does Not Own |
| --- | --- | --- |
| Shared | Cross-cutting technical primitives (correlation ID, future money/clock/pagination types) | Business workflows, product/order rules |
| Identity | Accounts, credentials, sessions, roles/permissions | Catalog content, payments |
| Catalog | Products, variants, categories, attributes, media (Day 9 local pipeline + manifests), derived Meilisearch search (Day 16) | Stock reservations, final payable prices, Meilisearch as source of truth |
| Inventory | Stock ledger, availability, reservations | Product descriptions, payments |
| Pricing | List/sale prices, promotions, coupon eligibility | Inventory quantities |
| Cart | Cart composition for a shopper session | Final order persistence, payment capture |
| Checkout | Checkout orchestration toward an order | Payment-provider integration details |
| Orders | Order lifecycle and immutable order snapshots | Payment-provider implementation |
| Payments | Payment intents, callbacks, reconciliation | Order-item composition |
| Shipping | Shipments, rates selection, fulfillment addressing | Inventory reservation rules |
| Hunting | Species, seasons, limits, legal sources | Geographic polygon storage |
| Geography | Zones, polygons, spatial queries | Legal interpretation, product ranking |
| Recommendations | Contextual ranking of products | Legal permission decisions |
| Content | CMS-like pages, articles, editorial blocks | Product canonical data |
| Notifications | Outbound email/SMS/push orchestration | Business decision of when an order exists |
| Operations | Admin/ops tooling, audits, support workflows | Customer storefront cart/checkout |

**Recommendations never determine whether hunting is legally allowed.** Hunting/legal modules remain authoritative for permission and limits; recommendations may only rank products within already-allowed context supplied by the API.

## Standard module structure

When a module gains real code, prefer this layout:

```text
DomainName/
├── Actions/       # One use case per class, public execute()
├── Contracts/     # Public interfaces / boundary types for other modules
├── DTOs/          # Immutable *Data objects (project convention)
├── Enums/
├── Events/        # Past-tense facts that already happened
├── Exceptions/
├── Models/        # Persistence owned by the module
├── Policies/
├── Queries/       # Non-trivial reads
├── Rules/
├── Services/      # Reusable domain behavior with a clear name
└── Support/       # Internal helpers (not a public API)
```

Not every directory is required on Day 2. Create folders when they have an immediate purpose.

### Layer type responsibilities

| Type | Responsibility |
| --- | --- |
| Actions | One application use case; `execute()`; no HTTP; typed input |
| DTOs (`*Data`) | Validated typed data between layers; no HTTP; no DB queries |
| Queries | Optimized reads; no writes; no HTTP responses |
| Services | Reusable domain behavior not belonging to one Action/model |
| Contracts | Explicit public boundaries and replaceable infrastructure |
| Models | Persistence owned by the module; no unrelated workflows |
| Events | Past-tense facts; identifiers preferred over full models |
| Policies | Authorization; deny by default; prefer permission checks over role-name comparisons |

See [authorization.md](authorization.md) for Day 5 RBAC, admin APIs, and audit logging.

**DTO naming convention:** suffix `Data` (for example `CreateProductData`). Do not mix `DTO` and `Data` suffixes.

**Public module API:** only documented classes under `Contracts/`, and Actions/Queries/DTOs explicitly marked as public in the module README, may be used by other modules.

## HTTP layer

```text
app/Http/
├── Controllers/Api/          # Existing health + future endpoints
│   └── V1/                   # Versioned feature controllers
├── Middleware/
├── Requests/Api/V1/
└── Resources/Api/V1/
```

Controllers accept HTTP, validate via Form Requests, authorize via Policies/Gates, map to `*Data`, call one Action or Query, and return an API Resource or small stable JSON. They must not contain business calculations, raw queries, `env()`, or return bare Eloquent models.

## Write request flow

```text
HTTP Request
→ Middleware (including correlation ID)
→ Form Request validation
→ Authorization
→ DTO (*Data)
→ Action::execute()
→ Domain services and models
→ Database transaction (owned by the Action)
→ Domain event (after successful commit for external side effects)
→ API Resource
→ HTTP Response (+ X-Request-ID)
```

## Read request flow

```text
HTTP Request
→ Middleware
→ Validation and authorization
→ Query
→ Read model or projection
→ API Resource
→ HTTP Response (+ X-Request-ID)
```

These flows are guidelines. Trivial endpoints may skip unused layers.

## Dependency direction

```text
Http (Controllers, Requests, Resources, Middleware)
    → Domains (Actions, Queries, Contracts, DTOs)
        → Shared (technical primitives only)
        → Framework / infrastructure via Laravel facades carefully inside domain edges
```

- Domain code must not depend on `App\Http`.
- `Shared` must not depend on business modules.
- Business modules may depend on `Shared`.
- Modules must not import another module’s internal implementation.
- Owned Eloquent `Models` may be referenced across modules for foreign-key relations and persistence identity; business behavior still goes through Contracts, Actions, Queries, DTOs, or Events.
- Production code must never depend on `Tests\`.
- `env()` only inside Laravel config files.

## Cross-module communication

Allowed and forbidden rules are defined in [ADR 0001](adr/0001-modular-monolith.md). Prefer synchronous contracts/Actions when a result is required immediately. Prefer events for “something happened” fan-out after commit.

## Shared module rules

`Shared` may hold genuinely shared technical or cross-domain concepts (correlation ID, future money/currency, clock, pagination primitives). It must not become a dumping ground. Do not add `Helper.php`, `Utils.php`, `CommonService.php`, `BaseRepository.php`, or `GeneralService.php` without a precise documented reason.

## Transaction ownership

- The Action that owns a write use case owns the database transaction.
- Controllers must not begin transactions.
- Do not hide transactions in unrelated low-level helpers.
- Do not perform external HTTP calls while a transaction is open.
- Dispatch external side effects only after a successful commit.
- Inventory, order, payment, and legal publication invariants require explicit transaction review when implemented.
- Cross-module writes require one coordinating Action and documented ownership.

## Event naming

- Past tense business names: `ProductPublished`, `InventoryReserved`, `OrderCreated`.
- Carry identifiers and minimal safe payload data.
- Prefer dispatching side effects after commit.
- A transactional outbox may be required later for critical delivery; not implemented on Day 2.

## Queue and job boundaries

- Jobs handle slow or retryable side effects and must be idempotent under retry.
- Prefer identifiers over large serialized model graphs.
- Define retry, timeout, and failure behavior; do not swallow exceptions.
- Critical business state must not depend on untracked fire-and-forget work.
- Outbox pattern may be introduced later for critical events.

## Logging

Structured fields for future logs:

```text
request_id, user_id, module, action, entity_type, entity_id, status, duration_ms
```

Never log passwords, tokens, payment credentials, full provider payloads, private documents, or unnecessary personal data. Prefer identifiers. Every API request has a `request_id` via correlation middleware.

## Background-job and extraction criteria

See ADR 0001 for extraction conditions. Until then, keep one deployable modular monolith.

## Allowed vs forbidden dependency examples

Allowed:

- `App\Http\Controllers\Api\V1\...` → `App\Domains\Catalog\Actions\CreateProductAction`
- `App\Domains\Checkout\Actions\...` → `App\Domains\Inventory\Contracts\...`
- `App\Domains\Orders\Actions\...` → `App\Domains\Shared\Support\CorrelationId`

Forbidden:

- `App\Domains\Catalog\...` → `App\Http\...`
- `App\Domains\Shared\...` → `App\Domains\Orders\...`
- `App\Domains\Cart\Services\...` → `App\Domains\Orders\Models\Order` (internal model)
- Any `App\...` → `Tests\...`
- Controllers calling `env()` or `DB::select(...)`

## Infrastructure concerns

| Concern | Why separate |
| --- | --- |
| MySQL | Strong consistency for transactional records |
| Redis | Cache, sessions, queues |
| Meilisearch | Search index separate from OLTP |
| Mailpit | Local email capture only |
| Local disk | Day 1/2 file storage; object storage can return later |

Day 2 does not implement catalog, auth, cart, checkout, payment, hunting, maps, or recommendations behavior—only architecture boundaries and correlation ID handling.
