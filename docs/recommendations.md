# Context-aware recommendations (Day 30)

Day 30 ranks the store's own products against a verified outdoor context. Ranking is deterministic. It does not decide whether hunting or fishing is legal.

See [ADR 0023](adr/0023-deterministic-contextual-recommendations.md).

## Authority

MySQL owns taxonomy terms, product assignments, compatibility rules, ranking profiles, merchandising rules, and simulation records. Meilisearch may suggest candidate product ids from the `{prefix}_recommendation_context` index. Every candidate is revalidated in MySQL before it can appear. A search outage falls back to MySQL candidates and still applies hard exclusions.

The storefront never sends a legal gate, a score, a price, or a stock figure as an instruction. `POST /api/v1/recommendations/contextual` rejects `gate`, `outcome`, `recommendations_allowed`, `score`, and coordinate fields.

## What was reused

- Catalog products, variants, publication, localized slugs, brands, and categories.
- `PublicCatalogPricing` and `PublicInventoryAvailability`. There is no backorder path, so an out-of-stock variant is not immediately purchasable.
- Cart add still uses `variant_id` and the existing cart API, which revalidates publication, price, and stock. A recommendation does not reserve inventory and is not stored on the cart as a legal guarantee.
- Legal conclusions (`allowed`, `prohibited`, `conditional`, `unknown`, `conflict`) and spatial evaluation. The map evaluate response now includes a short-lived HMAC `context_token`. The recommendation request sends that token, not the coordinate.
- Permissions, audit events, correlation ids, and the ka/en locale pair.
- Catalog attributes stay option axes. They are not the context taxonomy. A term may optionally point at a catalog category or attribute.

## Context taxonomy

`context_taxonomy_terms` stores a controlled code per dimension (activity, species, species category, equipment, method, season phase, region, zone type, and the other whitelisted dimensions). Translations use `context_taxonomy_term_translations`. The migration seeds the hunting and fishing vocabulary. It does not invent species-to-product mappings.

## Assignments and rules

`product_context_assignments` attaches a term to a product or a variant.

- Assignment types: `required_match`, `preferred_match`, `supported`, `neutral`, `excluded`.
- An `excluded` assignment defeats a positive assignment for the same term.
- A variant positive assignment replaces the product-level positive assignment for that variant. A product-level exclusion still applies.
- Source types keep manufacturer claims distinct from legal rules. Legal-derived rows keep a source reference.
- Draft rows do not affect the public index or public results.
- Active rows are unique per product, variant key, term, and assignment type. Variant key `0` means product-level.

`product_compatibility_rules` uses a whitelisted operator set (`equals`, `not_equals`, `in`, `not_in`, comparisons, `between`, `exists`, `not_exists`). There is no SQL, PHP, or template execution.

## Pipeline

`ContextualProductRecommender` runs these stages:

1. Validate placement, page, locale, and currency.
2. Apply the legal-outcome gate.
3. Build a bounded candidate pool from active assignments, category mappings, and optional Meilisearch ids.
4. Drop hard exclusions before scoring.
5. Score with the published profile weights.
6. Apply a bounded merchandising adjustment.
7. Break ties deterministically.
8. Attach a structured explanation and a separate confidence.
9. Return one page.

### Legal-outcome gate

| Context | Gate | Public result |
| --- | --- | --- |
| Prohibited or conflict | `recommendations_blocked` | No activity-enabling products |
| Unknown, or a boundary that is uncertain | `recommendations_unknown` | Map and outdoor-context placements suppress products. Species and season placements may show species-related gear labeled as unverified for location |
| Conditional, or allowed without spatial verification | `recommendations_information_only` | Compatible products with a restriction notice. No “buy now to hunt here” copy |
| Allowed, spatially verified, boundary clear | `recommendations_allowed` | Ranked products with normal commerce actions |

Only a token issued by spatial evaluate can set `spatially_verified`. Unsigned zone ids stay unverified. The token is HMAC-SHA256, expires in 15 minutes, and is rejected if it contains coordinates.

### Hard exclusions

Exclusion codes include unpublished or inactive products, unavailable variants, out of stock when the profile hides it, incompatible activity or species, prohibited equipment or method, region or zone restriction, sale restriction, expired assignments, manual exclusion, compatibility conflicts, and missing required product data. Public responses do not list rejected candidates. Admin simulation does.

### Score, confidence, and ties

Weights live on the published profile and must include every dimension, stay within 0–40, and sum to 100. The base score is 0–100. An activity-only match is capped (default 60). Merchandising may add at most the profile maximum, which cannot exceed 12, and the seeded profiles use 8. A boost, pin, or paid flag never restores an excluded product. Pins sort first among eligible products only.

Tie order:

1. Pinned, then pin priority.
2. Final score descending.
3. Confidence rank descending.
4. In stock.
5. Merchandising priority.
6. Publication time descending.
7. Slug ascending.

Confidence (`high`, `medium`, `low`, `insufficient`) is not legal confidence. `insufficient` is not returned as a contextual recommendation. Partial context downgrades high confidence.

Explanations use reason codes mapped to Georgian and English templates. Public cards expose the text, not the weight numbers. Admin simulation exposes score contributions.

A single deterministic eligible variant may be recommended. Otherwise `recommended_variant` is null and add-to-cart stays off until the shopper chooses on the product page. Prices are minor units in the storefront currency.

## Profiles and merchandising

Profile statuses: `draft`, `in_review`, `approved`, `published`, `superseded`, `archived`. One published profile is active per placement. Publishing supersedes the previous published profile for that placement. The author cannot approve or publish their own profile. Seeded default profiles have no author, so the initial public profiles are usable before an editor publishes a replacement.

Merchandising adjustments are `boost`, `demote`, `pin`, and `exclude`, with a start and end. Expired rules do not apply. Paid placements set `is_promoted` and a promotion label.

## API

Public:

- `POST /api/v1/recommendations/contextual`

Admin, permission-gated:

- Profile list, create, update, submit-review, approve, publish, supersede.
- Coverage, taxonomy terms, product assignments, bulk assignment, merchandising rules, simulation.

Default page size is 10. Public rate limit is 30 requests per minute. Simulation is 10 per minute.

## Cache and privacy

Cache keys use placement, profile version, locale, currency, page size, and the derived context. Latitude and longitude are stripped. TTL is 45 seconds with an 8 second lock. Assignment, profile, and merchandising writes bump `recommendations:version`. The public response is `Cache-Control: private, max-age=30`. Add-to-cart still reads live commerce data.

Logs and analytics omit coordinates, permits, licenses, and the full context payload. Simulation snapshots store zone public ids and derived signals.

## Admin and storefront

`/admin/recommendations` covers coverage, taxonomy, assignments, bulk mapping, profiles, merchandising, and simulation.

Public sections:

- Map location result, after the legal panel, using `context_token`.
- Species detail, labeled as species-related gear, with a link to the map. No location is assumed.
- Season explorer, using activity, species slug, period, and a short region code. The client does not send a legal conclusion.

There is no separate Day 29 planner page in this repository. `outdoor_context_result` is supported by the API for a later planner payload. The map placement is the location result.

Changing the token, species, or period changes the request key. The previous list is marked stale until the next response arrives.

## Operations

```bash
php artisan recommendations:reindex
```

Queue the search index after assignment changes if Meilisearch is enabled. Index failure does not publish a draft profile or skip exclusions. Draft assignments are not indexed.

## Tests

Backend: `tests/Unit/Recommendations/RecommendationEngineTest.php` and `tests/Feature/Recommendations/ContextualRecommendationTest.php`.

A local sqlite run with 12 fictional products recorded 128 queries and about 60 ms. That is not a production guarantee.

## Troubleshooting

- Empty public results: confirm a published profile for the placement and at least one active assignment. Unknown or prohibited gates intentionally return no activity products.
- A product vanishes after a boost: check for an exclusion. Merchandising cannot reverse one.
- Search looks narrow: Meilisearch is down or the recommendation index is empty. MySQL candidates still apply.
- The map section does not refresh: the evaluate response must include a new `context_token`. Do not send coordinates to the recommendation endpoint.
