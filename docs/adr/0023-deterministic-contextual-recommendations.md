# ADR 0023 — Deterministic contextual recommendations

## Status

Accepted (Day 30)

## Context

The catalog can already sell published, priced, stocked variants. Legal, season, and spatial modules can already describe an outdoor context. Those two sides were not connected. A recommendation that changed a legal conclusion, hid a prohibition, or ranked from an unreviewed model would be unsafe for this catalog.

## Decision

1. Ranking is a deterministic weighted score loaded from a versioned, reviewed profile. Machine-learning ranking, collaborative filtering, and behavioral personalization are deferred.
2. Legal evaluation stays in the legal and spatial modules. Recommendations read a derived conclusion and a gate. They never write a legal outcome, and the client cannot submit one.
3. Hard exclusions run before scoring. A prohibited product, an incompatible assignment, an unpublished product, or an unavailable variant cannot receive a merchandising boost.
4. Merchandising is a bounded integer adjustment with an expiry. Pins reorder eligible products only.
5. Meilisearch is an optional candidate generator. MySQL revalidates publication, assignments, exclusions, price, and stock.
6. Explanations are reason codes mapped to Georgian and English templates, not free-form marketing strings and not raw weights on the public card.
7. The recommendation endpoint does not require coordinates. The map sends the HMAC context token from spatial evaluate. Cache keys, logs, simulation snapshots, and analytics omit exact coordinates.
8. Confidence is a separate compatibility signal. It is not legal confidence.

## Consequences

- Editors must map products to the controlled taxonomy before contextual results appear. The system does not invent compatibility.
- Seeded profiles make the public endpoint work before a four-eyes publication. Replacing a profile still requires a different person to approve and publish.
- Candidate generation can miss products that have no assignment and are absent from the search index. That is preferred to a full catalog scan and to an unlabeled guess.
- Day 31 can build gear sets on assignments, exclusions, and the gate without replacing this ranker.
