# Official Georgian hunting, fishing, and protected-area data

Snapshot `2026-09-26.1` lives in `backend/database/data/official/georgia`. The manifest in that directory is the review copy: source URLs, document numbers, retrieval time, SHA-256 checksums, and review status. Production seeders read those files. They do not call the live websites.

Retrieved at **2026-09-25T21:10:00Z**. Review status of the snapshot is `extracted_unreviewed`. Reviewer is empty. Nothing in this snapshot is published.

## Sources

| Document | Identifier | Retrieved URL | What was stored |
| --- | --- | --- | --- |
| Ministerial Order No. 95, adopted 27/12/2013, registration `360160000.22.023.016220` | Matsne `2166315` | `https://www.matsne.gov.ge/ka/document/view/2166315?publication=12` | Consolidated publication dated 07/08/2024, amendment order `2-700`. Annex 1 species, periods, daily limits, municipality exceptions, article 3 methods, permits, and location text. |
| Ministerial Order No. 18, adopted 25/05/2009, registration `410.030.000.22.023.013.136` | Matsne `84040` | `https://www.matsne.gov.ge/ka/document/view/84040?publication=5` | The public response is the original form for 03/06/2009–04/09/2013. Matsne says the consolidated view is paid. The 2009 list is stored as historical and is not imported as the current hunting-object list. |
| MEPA news, 24/07/2026 | `26537` | `https://mepa.gov.ge/Ge/News/Details/26537` | 2026–2027 announcement. Annual confirmation, not a replacement for Order No. 95. |
| Law of Georgia on Wildlife, No. 540, adopted 25/12/1996, registration `410.000.000.05.001.000.186` | Matsne `33352` | `https://matsne.gov.ge/ka/document/view/33352?publication=20` | Consolidated publication 29/06/2023. Article 69 paragraph 2 subparagraph პ is the authority cited by Order No. 95. |
| Government Resolution No. 423, adopted 31/12/2013, registration `300160070.10.003.017645` | Matsne `2187155` | `https://www.matsne.gov.ge/ka/document/view/2187155?publication=16` | The public response is the original form for 10/01/2014–20/02/2014. The 22/06/2026 consolidation is paid. No fishing rule was extracted. |
| Law on the System of Protected Areas, No. 136, adopted 07/03/1996, registration `360.050.000.05.001.000.127` | Matsne `32968` | `https://matsne.gov.ge/ka/document/view/32968?publication=23` | Consolidated publication 28/05/2026. Article 3 category list only. Categories are not treated as identical hunting or fishing bans. |
| MEPA spatial catalogue and map | APA17, APA29, MEPA51, MEPA52 | `https://portal.mepa.gov.ge/Ge/User/data` and the map links in `spatial/datasets.json` | Layer metadata. No polygon file. |

Protected Planet (`https://www.protectedplanet.net/country/GEO`) was not retrieved and is not a source for any record.

HTML page checksums, including portal chrome, are `retrieved_pages` in `manifest.json`. Those HTML files are not committed. The structured JSON files are the snapshot, and each has its own SHA-256 in `manifest.files`.

## What was extracted

Order No. 95 Annex 1, consolidated text of 07/08/2024:

- 19 migratory bird species. Georgian names and scientific names are stored as printed. Parenthetical Georgian names are aliases. English common names were not in the source and were not added.
- 12 waterfowl species on the rest of Georgia, 01.11–01.03, excluding ახალქალაქი, ნინოწმინდა, წალკა, and დმანისი.
- The same 12 species only in those four municipalities, 10.09–10.11, with the annex's other daily limits.
- Woodcock, 15.10–15.12, daily limit 7.
- Snipe, quail, and four pigeon or dove species whose annex period begins on the third Saturday of August and runs until 15 February. No calendar date was calculated for that weekday rule.
- Article 3 paragraph 1 documents, paragraph 7 location text, paragraphs 8 and 9 prohibited methods.
- Daily limits use the annex unit `ცალი`. The annex does not define the unit in English. Linking the limit to a person follows article 3 paragraph 2 and stays in review.

The 2009 Order No. 18 text lists further birds and mammals, including spellings printed as `Martes Martes` and `procyon lotor`. Those rows stay in `hunting/hunting_objects.json` with `import_as_current: false`.

## Conflicts

Both sides are stored. Neither is published.

1. **August opening.** Order No. 95 article 3 paragraph 2 and the annex rows above say the third Saturday of August (`აგვისტოს მესამე შაბათი`). MEPA news 26537 says the fourth Saturday and the dates 22 August 2026 through 1 March 2027. 22 August 2026 is the fourth Saturday of that month. The recurring weekday rule has no season definition. The fixed dates are conditions on the announcement rule, also without a season definition.
2. **National-park buffer.** Article 3 paragraph 7 says 500 meters around state reserves and national parks. The announcement says 500 meters around reserves and 250 meters around national parks. No buffer polygon is generated.

The announcement also names the Nugzar Zazanashvili multi-use area and agency breeding farms, and it states a 10 GEL fee with treasury details. Those statements are stored on their own in-review rules. The retrieved article 3 paragraph 1 requires a fee receipt and a firearm document and does not state 10 GEL.

## Coverage gaps

Public results stay unknown for these. Missing data is not permission.

- Current consolidated Order No. 18.
- Current consolidated fishing regulation. Recreational and commercial rules were not mixed because neither was extracted.
- APA17, APA29, MEPA52, and MEPA51 polygons.
- City administrative boundaries named in article 3 paragraph 7.
- Municipality boundaries for the four annex municipalities.
- 500-meter and 250-meter buffers.
- English species names.

## Import

```bash
cd backend
php artisan official:import-georgia --dry-run
php artisan official:import-georgia
```

Sections: `sources`, `species`, `seasons`, `limits`, `restrictions`, `fishing`, `spatial`, `assignments`.

Seeders of the same names call those sections. They are not part of `DatabaseSeeder`. Running one section also loads the sections it depends on. A second run does not duplicate rows and does not overwrite a published or reviewed record.

Rules, species, seasons, and document versions stay `in_review` or draft. High-severity open conflicts block `LegalPublicationValidator`. Occurrences are generated only after a season definition is published, so the public calendar does not show this import until an administrator publishes a specific reviewed rule.

## GIS export

The catalogue detail page for APA17 (`https://portal.mepa.gov.ge/Ge/User/DataDetails?geoDataId=17`) has no geometry download. Its placement link is `https://portal.mepa.gov.ge/Ge/user/map/10`. The catalogue button `ექსპორტი` exports the layer list, not the polygons. APA29 uses the same map. MEPA51 (`Ramsar`) and MEPA52 (`SPA_Birds`) are on `https://portal.mepa.gov.ge/Ge/user/map/9`.

Export the layer from the map's own control, or request the file from the publisher named in the catalogue. Save GeoJSON, EPSG:4326, longitude then latitude, with a stable identifier property. Do not guess a service URL.

Artisan reserves `--version`, so the version label flag is `--version-label`.

```bash
php artisan spatial:import \
  --source=/absolute/path/protected_areas.geojson \
  --dataset=protected-areas-georgia \
  --version-label=2026-04-22 \
  --source-code=APA17 \
  --dry-run

php artisan spatial:import \
  --source=/absolute/path/protected_areas.geojson \
  --dataset=protected-areas-georgia \
  --version-label=2026-04-22 \
  --source-code=APA17 \
  --identifier-property=THE_STABLE_ID_FIELD \
  --name-property=THE_NAME_FIELD \
  --actor=ADMIN_USER_ID
```

Repeat with `protected-area-functional-zones-georgia` / `APA29`, `bird-special-protection-areas-georgia` / `MEPA52`, and `ramsar-wetlands-georgia` / `MEPA51`.

The command validates GeoJSON, stores the original file, records SHA-256, and imports draft zones. It does not publish them. A second import of the same checksum is skipped. Features without the identifier property are rejected. A code that is not in the catalogue is stored as fictional so a test polygon is not labeled as an official boundary.

`CanonicalBufferGuard` records a requested buffer distance and does not build geometry.

## Review and publication

In the admin legal rules list, filter `status=in_review`. Conflicts are open and high. Spatial datasets for the four layers exist as drafts with no version until a file is imported. Draft zones are absent from `GET /api/v1/spatial/zones`.

Publication uses the existing legal and spatial workflows. Do not publish either side of an open high-severity conflict. Do not publish a protected-area category as a hunting ban unless a cited provision says so for that category. Functional zones need their own rules after APA29 geometry is reviewed.

## Rollback

The import does not delete reviewed rows. To undo an unreviewed import, delete the draft documents, rules, species, and spatial sources whose notes start with `official-georgia:2026-09-26.1`, or restore a database backup taken before `official:import-georgia`. Do not edit this snapshot's JSON in place after it has been approved. Add `2026-09-26.2` or a later directory, point the importer at it, and keep the previous version rows.

## Next annual season

Add `hunting/annual_seasons/YYYY-YYYY.json` in a new snapshot version. Copy the official dates from that year's MEPA announcement. Do not calculate them from the weekday rule while the third-Saturday and fourth-Saturday texts still disagree. Leave the new rule in review, cite the announcement, and open a conflict if it still disagrees with the consolidated order.

## Detecting source changes

Matsne and MEPA sources are marked for monitoring but stay unverified, so `legal:check-sources` does not fetch them until an administrator verifies the source. After verification, a checksum or text change becomes a change-detection row and does not publish rules. Compare the new Matsne consolidated publication date with `version_label` on the document before editing a snapshot.
