# Legal information (Day 25)

Day 25 adds official legal sources, immutable document versions, hierarchical provisions, structured rules, conflict detection, and point-in-time evaluation.

This is **informational**. The platform does not provide legal advice. Absence of a prohibition is not permission. Missing evidence returns `unknown`.

See [ADR 0019](adr/0019-versioned-legal-rules.md) and `backend/app/Domains/Legal/README.md`.

## Patterns reused

- Laravel modular domain (`App\Domains\Legal`) with public contracts: Enums, Models, Queries, DTOs.
- UUID `public_id` on API resources; integer PKs internally.
- `/api/v1` envelopes `{ data, meta }` and `ApiErrorResponse` codes.
- Pagination default 10, max 50.
- Sanctum SPA auth, `EnsureHasPermission`, Spatie roles via `Permission` + `RolePermissionMatrix`.
- `RecordAuditEventAction` + `AuditEvent`.
- Form requests, thin controllers, domain exceptions.
- Private local disk (same pattern as catalog media, separate disk because legal files include PDFs).
- Redis cache version bump (`legal:public:version`), never the source of truth.
- Queue workers + scheduler (`legal:check-sources` daily).
- Pest feature/unit tests and Vitest admin/storefront tests.

## What was introduced

- Domain tables for authorities, sources, documents, versions, provisions, rules, citations, typed conditions, limits, exceptions, reviews, conflicts, change detections.
- Private disk `legal_private` (`storage/app/private/legal`).
- Admin workspace `/admin/legal`.
- Public `GET /api/v1/legal/sources`, `POST /api/v1/legal/evaluate`, `GET /api/v1/species/{slug}/legal-overview`.
- Species detail `legal_information` is filled from published rules (list cards remain a stub).

## Assumptions

- Default jurisdiction is `GE`. Controlled vocabulary is `config/legal.php` `jurisdictions`.
- `LEGAL_REQUIRE_DISTINCT_PUBLISHER` defaults to false so a legal editor can complete local workflows. Enable in production for four-eyes publication.
- Malware scanning infrastructure is not present. Uploads are type/size validated; `config/legal.php` `storage.malware_scan` is false and is an extension point only.
- Optional Meilisearch legal discovery is **not** used for evaluation. `search:rebuild-legal` is a recovery no-op until a verified public source set exists.
- Development authorities created through admin default to `is_fictional=true`.

## Intentionally deferred (Day 28+)

- Interactive public map, clustering, GPS permission, and offline tiles.
- Product recommendations from legal or season outcomes.
- Email/calendar notifications.
- Automatic publication after source changes (will never happen without human review).

Day 27 spatial APIs are documented in [spatial.md](spatial.md) and [ADR 0021](adr/0021-mysql-spatial-zones.md).

## Data model (MySQL is authoritative)

| Table | Role |
| --- | --- |
| `legal_authorities` | Issuing body. Fictional rows must set `is_fictional`. |
| `legal_sources` | Official origin. Must be verified + active to support published rules. |
| `legal_documents` | Logical law/notice. Status does not alone decide rule validity. |
| `legal_document_versions` | Immutable snapshot after approval. SHA-256 checksum. Private file. |
| `legal_provisions` | Addressable article/section. Official text ≠ editorial summary. |
| `legal_rules` | Machine-readable effect/scope/dates. Only `published` is public. |
| `legal_rule_citations` | Rule → provision. Primary authority citation required to publish. |
| `legal_rule_conditions` | Typed operators; never executable JSON. |
| `legal_rule_limits` | Bag/catch/size-style numeric limits. |
| `legal_rule_exceptions` | Explicit reviewed precedence. No silent recency override. |
| `legal_reviews` | Durable review records. |
| `legal_conflicts` | Persisted overlaps. High-severity open conflicts block publish. |
| `legal_change_detections` | Human review items for remote metadata changes. |

## Publication workflow

`draft → in_review → approved → published → superseded|archived`

Publication requires: interpretation summary, reviewer, approved primary citation, verified source, approved version/provision, valid dates, no blocking high-severity conflict.

Published rules are not freely edited. Replace via a new rule and `supersede`.

## Evaluation outcomes

`allowed` | `prohibited` | `conditional` | `unknown` | `conflict`

- Only published, time-effective rules.
- A matching prohibition wins over a general permission.
- No matching evidence → `unknown` (never inferred `allowed`).
- Missing required facts → `conditional` or `unknown`.
- Unresolved high-severity conflicts among matched rules → `conflict`.

Every public response includes `disclaimer` = `legal.informational_not_advice`.

## Files

- Disk: `legal_private`
- Allowed: PDF, TXT, HTML; 20MB; no double extensions; generated storage names
- Downloads: `GET /api/v1/admin/legal/versions/{id}/download` (authorized, `X-Content-Type-Options: nosniff`)
- URL retrieval: HTTPS only, approved domain, no credentials/custom ports, DNS + private-IP block, redirect cap

## Authorization

Granular `legal.*` permissions. `legal-editor` authors, reviews, and can publish. `legal.rules.supersede` and `legal.conflicts.resolve` remain admin-only via the full admin permission set. Server policies enforce every action.

Run after deploy:

```bash
php artisan access-control:sync
```

## Operations

```bash
php artisan migrate
php artisan access-control:sync
php artisan legal:check-sources
php artisan search:rebuild-legal
```

Queue worker must run (`QUEUE_CONNECTION=redis`). Scheduler: `legal:check-sources` daily.

Private legal files are **not** linked publicly. Do not `storage:link` the legal disk.

## Testing

```bash
cd backend && PAO_DISABLE=true php artisan test --compact tests/Unit/Legal tests/Feature/Legal
cd frontend && npm test -- src/features/legal src/features/species/tests/SpeciesPages.test.tsx src/features/admin/tests
```

Official Georgian texts for Order No. 95, the wildlife law, the protected-areas law, and the 24 July 2026 ministry announcement are in snapshot `2026-09-26.1`. See [official Georgian data](official-georgia.md). The import stays in review. The current consolidated hunting-object list and fishing regulation were not public and were not replaced with fictional rules.

## Security

- No raw storage paths in APIs
- Drafts excluded from public endpoints
- Internal notes hidden on models
- SSRF controls on retrieval/monitoring
- Rate limits: `legal.public`, `legal.evaluate`, `legal.admin-download`
- Audit events for source/version/rule/conflict/change actions
