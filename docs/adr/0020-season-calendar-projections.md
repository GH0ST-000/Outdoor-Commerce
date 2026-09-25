# ADR 0020 — Season definitions vs generated occurrences

## Status

Accepted (Day 26)

## Context

The public product must answer period questions such as “what can I hunt or fish during these dates?” Legal sources describe schedules (fixed ranges or annually recurring patterns), not a pre-materialized list of every future day. Administrators must not invent seasons. Evaluation must stay deterministic in `Asia/Tbilisi`, auditable, and fast enough for a public explorer.

## Decision

1. **Definitions stay authoritative; occurrences are projections.** A `legal_season_definitions` row (tied to a Day 25 legal rule and citations) is the source of truth. `legal_season_occurrences` are generated, idempotent, and safe to rebuild. They are not edited directly.

2. **Half-open intervals internally.** Date-only rules open at local midnight and close inclusively through the stated local day. Storage and overlap queries use `[starts_at, ends_at_exclusive)`. This avoids end-of-day timezone bugs and keeps indexed overlap predicates (`starts_at < queryEnd AND ends_at_exclusive > queryStart`) sargable.

3. **Cross-year seasons belong to the opening year.** A 1 November–31 January season is season year Y for the November opening, ending in January of Y+1.

4. **Bounded projection horizon.** Generate the previous year, current year, and next three years (`config/legal.php` `calendar.horizon_*`). Unlimited future rows would encode unpublished future law. Future occurrences always display last-verification metadata.

5. **MySQL remains authoritative.** Redis (`LegalPublicCache`) caches published public payloads only. A cache miss recomputes from MySQL. A cache failure never turns `unknown` into permission.

6. **Meilisearch does not decide availability.** Search may later help species discovery. Date overlap and legal state are evaluated in MySQL + domain services. A search outage cannot invent a legal conclusion.

7. **Missing records are `unknown`.** Absence of a season row is not a prohibition and is not permission. `closed` requires published closure or prohibition evidence.

8. **Temporary closures are source-backed overrides.** They do not rewrite the parent definition. They require their own published legal rule, citations, review, and explicit precedence when they overlap contradictory openings.

## Consequences

- Regeneration after publish/supersede is mandatory and locked.
- Administrators preview occurrences without publishing.
- Public APIs expose windows, citations, verification timestamps, and a disclaimer.
- Day 27 can attach spatial `zone_reference` matching without changing this calendar core.
