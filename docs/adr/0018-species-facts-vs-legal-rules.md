# ADR 0018 — Separation of biological species knowledge from legal hunting and fishing rules

## Status

Accepted (Day 24)

## Context

The Hunting module will eventually own species, seasons, limits, and official legal sources. Day 24 introduces a bilingual species knowledge base for identification and natural history. Later days add:

- Day 25: legal rules and official sources
- Day 26: hunting and fishing calendars
- Day 27–28: geographic zones and maps

Mixing those concerns on the species record would freeze legal conclusions into biological pages, imply permission from activity type or conservation status, and make seasonal law changes rewrite species content.

## Decision

Species records store biological facts, taxonomy, identification, habitats, conservation assessments with provenance, and editorial workflow. They do **not** store seasons, bag limits, permits, weapon rules, zone permissions, or “you may hunt this today.”

Every public species projection includes a typed `legal_information` object. In Day 24 it is always:

```json
{ "available": false, "message_key": "species.legal_information_not_yet_available" }
```

Days 25–26 may set `available: true` on species **detail** when published, source-backed rules exist. They must not write legal conclusions onto `species` rows. List cards may keep the stub. Evaluation lives in the Legal domain.

## Consequences

- Species pages remain useful when law is unknown, stale, or jurisdiction-specific.
- Conservation status and `activity_type` stay educational classifications.
- Legal rules can version independently (dates, jurisdictions, official sources).
- Frontend must not infer legality from products, conservation codes, or wildlife vs hunting labels.
