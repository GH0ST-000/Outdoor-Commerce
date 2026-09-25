# Hunting module

## Responsibility

Species knowledge (Day 24), and later seasons, limits, and legal source material.

Day 24 ships **biological species facts only**. Hunting/fishing permission is out of scope. See [ADR 0018](../../../../docs/adr/0018-species-facts-vs-legal-rules.md) and [docs/species.md](../../../../docs/species.md).

## Data owned

Species, translations, aliases, characteristics, habitats, identification traits, similar-species links, conservation assessments, reusable knowledge sources/citations, species revisions, species media attributions.

Seasons, bag limits, and legal interpretation remain future work.

## Public contracts

Models, enums, actions, queries, and DTOs under this module. HTTP lives in `App\Http`.

## Events this module may publish

`SpeciesChanged`, `SpeciesPublished`, `SpeciesUnpublished`, `SpeciesArchived`.

## May depend on

Shared; Identity permission names; Operations audit; Catalog media **Actions/Models/Enums** and search `SearchGateway` contract.

## Explicitly outside this module

Polygon geometry, product ranking, “what can I hunt today?”, seasons, maps.
