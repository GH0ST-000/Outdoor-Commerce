# Species Knowledge Base

Day 24 adds a bilingual (ka/en) biological species knowledge base. It is educational identification content, not hunting or fishing permission.

See [ADR 0018](adr/0018-species-facts-vs-legal-rules.md).

## Authority

- MySQL is the source of truth.
- Meilisearch holds published, locale-specific search projections only.
- Laravel owns publishing, sanitization, and visibility.

## Schema (core)

- `species` — identity, scientific taxonomy fields, publication/verification, content version, soft deletes
- `species_translations` — localized copy; one row per species+locale
- `species_aliases` — discoverability; canonical names stay on translations
- `species_characteristics` — nullable measurements with explicit units
- `habitats` / `habitat_translations` / `species_habitats` — controlled vocabulary
- `species_identification_traits`
- `species_similar` — no self-links; inverse duplicates rejected
- `species_conservation_assessments` — scoped (global/national/regional) and source-backed
- `knowledge_sources` / `knowledge_citations` — reusable provenance
- `species_revisions` — append-only editorial snapshots
- `species_media_attributions` — license/attribution on Catalog `media_attachments`

Scientific-name uniqueness is enforced on `scientific_name_normalized` across **all** rows, including archived and soft-deleted records. Slugs are stable; display-name edits do not rewrite `canonical_slug`.

## Taxonomy

Validated scientific fields plus `TaxonomyService` (option 2). No nested taxonomy-node tree. Rank values are scientific identifiers, never translated labels.

Allowed kingdoms: Animalia, Plantae, Fungi. Genus and epithet must match the binomial.

## Translation policy

Georgian (`ka`) is required and must be **published** before the species can be published. English may fall back to published Georgian copy when `SPECIES_ENGLISH_FALLBACK=true`. Unpublished translations never appear publicly.

## Aliases

Canonical common names live on translations. Public aliases may render. Search-only aliases stay hidden. Scientific synonyms require a `knowledge_sources` row. Regional Georgian spelling is stored exactly; normalization is for duplicate detection only. Transliterations are not auto-generated.

## Conservation

Global and Georgian/national assessments are separate rows (`assessment_scope`). Conservation status is never copied into a legal-permission field.

## Editorial workflow

`draft → in_review → published → archived`

Valid transitions: draft→in_review, in_review→draft, in_review→published, published→in_review, published→archived, archived→draft.

Publishing requires: scientific name, published Georgian common name + summary, at least one citation, primary media **or** `no_media_required`, valid slug, verification at least `partially_verified`, taxonomy consistency. Unpublish/archive require a reason. Optional two-person review: `SPECIES_REQUIRE_DISTINCT_REVIEWER`.

Restoring a revision writes a **new** revision.

## Search

Indexes: `{prefix}_species_{ka|en}_v1` (testing prefix includes `_test`).

```bash
php artisan search:rebuild-species
php artisan search:rebuild-species --locale=ka
php artisan search:verify-species
```

Unpublish/archive deletes search documents after commit.

## Public API

- `GET /api/v1/species`
- `GET /api/v1/species/{slug}`
- `GET /api/v1/species/{slug}/similar`
- `GET /api/v1/species/filters`

Default `per_page` is 10 (max 50). Drafts and archived records never appear. Species **list** cards keep `legal_information.available: false`. Species **detail** fills `legal_information` from published Legal-domain rules (Day 25); if none exist the outcome is `unknown`.

## Admin API

`/api/v1/admin/species` plus submit-review, publish, unpublish, archive, aliases, sources, similar-species, media, revisions.

Permissions: `species.view|create|update|delete|review|publish|archive|manage_taxonomy|manage_aliases|manage_sources|manage_media|view_revisions|restore_revision`.

Legal editor receives the species permissions. Catalog manager may view. Admin receives all.

## Frontend

- `/species` directory
- `/species/{slug}` detail
- `/admin/species` workspace

## Out of scope

Seasons, limits, quotas, permits, maps, polygons, product recommendations, image recognition.

## Content-entry guidelines

- Cite every quantitative claim and conservation assessment.
- Do not scrape copyrighted descriptions.
- Do not invent measurements or Georgian occurrence.
- Mark unverified content as unverified.
- Do not imply legality from activity type, products, or IUCN/national conservation codes.

## Local testing

```bash
cd backend && php artisan migrate && php artisan db:seed --class=HabitatVocabularySeeder
php artisan search:rebuild-species
```

Storefront: `http://localhost:3000/species`.
