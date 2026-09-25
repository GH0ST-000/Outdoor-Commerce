# Season calendar (Day 26)

Day 26 adds hunting and fishing **season definitions**, derived **occurrences**, reviewed **overrides**, and a public period explorer.

This remains **informational**. The platform does not provide legal advice. Missing season records return `unknown`, never permission or closure.

See [ADR 0020](adr/0020-season-calendar-projections.md) and `backend/app/Domains/Legal/README.md`.

## Relationship to Day 25

Season definitions belong to one structured Day 25 `legal_rules` row. The rule carries effect, citations, conditions, and limits. Only **published** rules and **published** seasons affect public results. Source-change detection still never publishes.

## Patterns reused

- Legal domain, UUID `public_id`, `/api/v1` envelopes, pagination default 10.
- `LegalRuleStatus` workflow (`draft` → `in_review` → `approved` → `published` → `superseded`).
- `LegalPublicCache` version bump (`legal:public:version`). MySQL stays authoritative.
- `LegalRuleStateMachine`, `LegalAuditRecorder`, `Permission` + `RolePermissionMatrix`.
- `Asia/Tbilisi` from `config/legal.php`.

## Season-specific architecture

| Concept | Role |
| --- | --- |
| Season definition | Authoritative source-backed schedule (fixed or annually recurring). |
| Season occurrence | Derived half-open interval for one season year. Regeneratable. |
| Season year | Opening year for cross-year ranges. |
| Override | Source-backed closure, special opening, or adjustment. Does not rewrite the definition. |
| Availability mode | `any_date`, `entire_period`, `timeline`. |

## Date-boundary policy

- Date-only opening: start of the local day in `Asia/Tbilisi`.
- Date-only closing: inclusive through the end of that local day.
- Stored internally as half-open `[starts_at, ends_at_exclusive)`.
- Exact times are stored and evaluated; they are not reduced to date-only ranges.
- Recurring 29 February generates **only** in leap years. No silent 28 February / 1 March fallback.
- Public queries are capped at 366 inclusive days (`LEGAL_CALENDAR_MAX_PUBLIC_DAYS`).

## Period evaluation

Calendar states: `open`, `partially_open`, `closed`, `conditional`, `unknown`, `conflict`.

- No matching published occurrence is **not** closed. Unfiltered lists omit species without evidence; a requested species without evidence is `unknown`.
- `entire_period` is `open` only when every segment is supported as open.
- Partial overlap is never labelled as fully open.
- Unresolved overlapping permission and prohibition without explicit precedence is `conflict`.

Meilisearch is **not** used to decide date overlap.

## Public API

- `GET /api/v1/outdoor/availability`
- `GET /api/v1/outdoor/calendar`
- `GET /api/v1/outdoor/season-transitions`
- `GET /api/v1/species/{slug}/seasons`

Storefront: `/seasons` (canonical). `/hunting-calendar` redirects there.

## Admin API

Under `/api/v1/admin/legal/seasons*`, `season-occurrences`, `season-overrides`, `calendar-generation-runs`, `calendar/evaluate-preview`, `calendar/coverage`.

Occurrences are read-only. Change the definition or publish an override.

## Jobs and scheduler

```bash
php artisan legal-calendar:generate [--from=] [--through=] [--jurisdiction=] [--dry-run]
php artisan legal-calendar:verify-projections
php artisan legal-calendar:extend-horizon
```

Scheduled daily with `withoutOverlapping`:

- `legal-calendar:extend-horizon`
- `legal-calendar:verify-projections`

Production also needs queue workers on `legal.calendar.queue` (default `default`).

## Cache

Public availability/calendar/transition responses use `LegalPublicCache`. Publication, supersession, override publication, and regeneration bump the version. Cache failure must not invent permission.

## Authorization

New permissions: `legal.seasons.*`, `legal.season_overrides.*`, `legal.calendar.*`. Legal editors can draft/review/publish/generate. Supersede remains admin (`legal.seasons.supersede`). Route middleware is authoritative.

## Demo data

No Georgian hunting or fishing seasons are seeded. Tests use fictional species (`Testus …`) and fictional jurisdictions/rules marked as test-only. Production calendars stay empty until verified sources are entered — the explorer then shows unknown, not “closed”.

## Known limitations / Day 27

- `zone_reference` is stored but not spatially matched.
- Region codes are strings; no polygon intersection.
- Meilisearch does not index season windows.
- Product recommendations are not attached to legal conclusions.

## Recovery

If projections look wrong: `legal-calendar:generate` then `legal-calendar:verify-projections`. Inspect `legal_calendar_generation_runs`. Do not edit occurrence rows by hand.
