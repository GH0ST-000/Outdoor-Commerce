# ADR 0019 — Versioned official legal sources and deterministic rule evaluation

## Status

Accepted (Day 25)

## Context

Day 24 stores biological species facts and explicitly refuses to encode hunting or fishing permission on species rows ([ADR 0018](0018-species-facts-vs-legal-rules.md)). Day 25 must answer bounded legal questions using official sources without pretending to be a lawyer, scraping prohibited content, or letting search indexes become the authority.

Legal text changes, is cited by article, and is high-risk. Treating it like product copy would mix interpretation with source text, allow unpublished drafts to leak, and let the engine guess when evidence is missing.

## Decision

1. **Official source text and editorial interpretation stay separate.** Provisions store `official_text` and an optional `normalized_summary` labeled editorial. Rules store `interpretation_summary` for public explanation. Evaluation never treats summaries as the law.

2. **Published rules require exact versioned citations.** A rule cannot be published without a primary authority citation to an approved provision on an approved document version of a verified, active source.

3. **Missing information returns `unknown`.** Absence of a prohibition is not permission. The evaluator never infers `allowed` from an empty match set.

4. **Human review is required before anything becomes public.** Source-change detection creates review items only. It never approves versions or publishes rules. Invalid state transitions fail.

5. **MySQL remains authoritative.** Redis caches published public views with a version key. Meilisearch, if used later, is discovery only. Evaluation always reads published MySQL rows.

6. **Official files use a private local disk.** No S3/MinIO in Day 25. Downloads go through authorized endpoints. Storage paths are never public.

7. **Typed conditions only.** Operators and value types are whitelisted. Arbitrary JSON, SQL, PHP, or JavaScript is rejected.

8. **Unresolved conflicts are not silently resolved.** Overlapping contradictory published rules persist as conflicts. High-severity open conflicts block publication. Evaluation returns `conflict` unless reviewed precedence or an explicit exception exists.

## Consequences

- Species pages can show a legal overview without writing conclusions onto `species`.
- Historical versions remain auditable.
- Day 26 calendars can attach seasons to the same versioned citations.
- Production must not seed invented regulations as real.
