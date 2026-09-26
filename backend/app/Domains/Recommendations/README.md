# Recommendations module

## Responsibility

Deterministic, explainable ranking of published catalog products for a derived outdoor context. Day 30.

Legal allow, deny, and conflict decisions stay in the Legal and Geography modules. This module reads a derived conclusion and applies a recommendation gate. It does not change the legal outcome.

## Data owned

- `context_taxonomy_terms` and translations
- `product_context_assignments`
- `product_compatibility_rules`
- `recommendation_profiles` and `recommendation_profile_weights`
- `recommendation_merchandising_rules`
- `recommendation_simulations`

MySQL is authoritative. The optional `{prefix}_recommendation_context` Meilisearch index only proposes candidates.

## Public entry points

- `App\Domains\Recommendations\Services\ContextualProductRecommender`
- `App\Domains\Legal\Actions\IssueOutdoorContextTokenAction`
- `App\Domains\Legal\Actions\VerifyOutdoorContextTokenAction`
- `App\Domains\Legal\Queries\ResolveDerivedLegalContextQuery`
- `App\Domains\Legal\DTOs\DerivedLegalContextData`

Other modules should use these actions, queries, and DTOs rather than recommendation services' internals.

## Explicitly outside this module

Legal conclusions, spatial geometry, inventory reservation, cart contents, and machine-learning rankers.

See [docs/recommendations.md](../../../../docs/recommendations.md) and [ADR 0023](../../../../docs/adr/0023-deterministic-contextual-recommendations.md).
