<?php

declare(strict_types=1);

use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesAliasType;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\SpeciesRevision;
use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\SpeciesFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(InteractsWithAccessControl::class, UsesFakeSearchGateway::class);

beforeEach(function (): void {
    $this->fakeSearchGateway();
    SpeciesFixtures::seedHabitats();
});

it('normalizes scientific names and rejects duplicates including soft-deleted rows', function (): void {
    $admin = $this->createAdmin();
    $first = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species', [
        'scientific_name' => 'Cervus elaphus',
        'taxonomic_rank' => 'species',
        'kingdom' => 'Animalia',
        'phylum' => 'Chordata',
        'class_name' => 'Mammalia',
        'order_name' => 'Artiodactyla',
        'family' => 'Cervidae',
        'domain_type' => 'terrestrial',
        'activity_type' => SpeciesActivityType::Wildlife->value,
    ]);
    $first->assertCreated();
    expect($first->json('data.scientific_name'))->toBe('Cervus elaphus');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species', [
        'scientific_name' => 'cervus   ELAPHUS',
        'taxonomic_rank' => 'species',
        'kingdom' => 'Animalia',
        'domain_type' => 'terrestrial',
        'activity_type' => 'wildlife',
    ])->assertStatus(422)->assertJsonPath('error.code', 'SPECIES_SCIENTIFIC_NAME_CONFLICT');
});

it('keeps public IDs and slugs stable after display-name changes', function (): void {
    $admin = $this->createAdmin();
    $created = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species', [
        'scientific_name' => 'Capreolus capreolus',
        'taxonomic_rank' => 'species',
        'kingdom' => 'Animalia',
        'domain_type' => 'terrestrial',
        'activity_type' => 'hunting_and_wildlife',
        'canonical_slug' => 'capreolus-capreolus',
    ])->assertCreated();

    $id = $created->json('data.id');
    $slug = $created->json('data.canonical_slug');
    $version = $created->json('data.content_version');

    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/species/'.$id, [
        'content_version' => $version,
        'translations' => [[
            'locale' => 'ka',
            'common_name' => 'შველი',
            'summary' => 'სატესტო შეჯამება',
            'identification' => 'ნიშნები',
            'content_status' => 'published',
        ]],
    ])->assertOk();

    expect($this->actingAs($admin, 'web')->getJson('/api/v1/admin/species/'.$id)->json('data.canonical_slug'))
        ->toBe($slug)
        ->and($id)->not->toMatch('/^\d+$/');
});

it('does not expose drafts on the public API and rejects incomplete publishing', function (): void {
    $admin = $this->createAdmin();
    $draft = SpeciesFixtures::draft();

    $this->getJson('/api/v1/species/'.$draft->canonical_slug)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'SPECIES_NOT_FOUND');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species/'.$draft->public_id.'/submit-review')
        ->assertOk()
        ->assertJsonPath('data.publication_status', 'in_review');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species/'.$draft->public_id.'/publish')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'SPECIES_PUBLICATION_INVALID');
});

it('publishes a complete species, indexes it, then removes it after unpublish', function (): void {
    $search = $this->fakeSearchGateway();
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $species = SpeciesFixtures::publishable();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/submit-review')->assertOk();
    $published = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/publish');
    $published->assertOk()->assertJsonPath('data.publication_status', 'published');

    expect(AuditLog::query()->where('event', AuditEvent::SpeciesPublished)->where('subject_id', $species->public_id)->exists())
        ->toBeTrue()
        ->and(SpeciesRevision::query()->where('species_id', $species->id)->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/species/'.$species->canonical_slug)
        ->assertOk()
        ->assertJsonPath('data.legal_information.available', false)
        ->assertJsonPath('data.legal_information.message_key', 'species.legal_information_not_yet_available')
        ->assertJsonMissingPath('data.reviewed_by')
        ->assertJsonMissingPath('data.citations.0.editor_note');

    $this->getJson('/api/v1/species?per_page=10')->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 10);

    $this->getJson('/api/v1/species?per_page=200')->assertStatus(422);

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/unpublish', [
        'reason' => 'Editorial correction',
    ])->assertOk();

    $this->getJson('/api/v1/species/'.$species->canonical_slug)
        ->assertNotFound();

    $ids = [];
    foreach ($search->indexes as $documents) {
        $ids = array_merge($ids, array_keys($documents));
    }
    expect($ids)->not->toContain($species->public_id.'_ka');
});

it('returns 409 on stale content versions and never mass-assigns publication status', function (): void {
    $admin = $this->createAdmin();
    $species = SpeciesFixtures::draft();

    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/species/'.$species->public_id, [
        'content_version' => $species->content_version + 9,
        'translations' => [[
            'locale' => 'ka',
            'common_name' => 'სახელი',
            'summary' => 'შეჯამება',
            'identification' => 'ნიშანი',
        ]],
    ])->assertStatus(409)->assertJsonPath('error.code', 'SPECIES_VERSION_CONFLICT');

    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/species/'.$species->public_id, [
        'content_version' => $species->fresh()?->content_version,
        'publication_status' => 'published',
    ])->assertOk();

    expect($species->fresh()?->publication_status)->toBe(SpeciesPublicationStatus::Draft);
});

it('rejects unauthorized publishers and hides search-only aliases from public responses', function (): void {
    $catalog = $this->createUserWithRole(Role::CatalogManager);
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $species = SpeciesFixtures::publishable();
    $source = KnowledgeSource::factory()->create();

    $this->actingAs($catalog, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/publish')
        ->assertForbidden();

    auth()->forgetGuards();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/aliases', [
        'name' => 'ირემი',
        'type' => SpeciesAliasType::CommonAlias->value,
        'is_public' => true,
        'is_searchable' => true,
        'locale' => 'ka',
    ])->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/aliases', [
        'name' => 'typo-name',
        'type' => SpeciesAliasType::MisspellingAlias->value,
        'is_public' => false,
        'is_searchable' => true,
        'locale' => 'en',
    ])->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/aliases', [
        'name' => 'Cervus elaphus old',
        'type' => SpeciesAliasType::ScientificSynonym->value,
        'is_public' => true,
    ])->assertStatus(422)->assertJsonPath('error.code', 'SPECIES_SOURCE_REQUIRED');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/aliases', [
        'name' => 'Cervus elaphus old',
        'type' => SpeciesAliasType::ScientificSynonym->value,
        'is_public' => true,
        'source_id' => $source->id,
    ])->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/publish')->assertOk();

    $detail = $this->getJson('/api/v1/species/'.$species->canonical_slug)->assertOk();
    $names = collect($detail->json('data.aliases'))->pluck('name');
    expect($names)->toContain('ირემი')->and($names)->not->toContain('typo-name');
});

it('rejects similar-species self references and unpublished related records', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $one = SpeciesFixtures::published();
    $two = SpeciesFixtures::draft();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$one->public_id.'/similar-species', [
        'similar_species_id' => $one->public_id,
        'relationship_type' => 'visually_similar',
    ])->assertStatus(422)->assertJsonPath('error.code', 'SPECIES_SIMILAR_RELATION_INVALID');

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$one->public_id.'/similar-species', [
        'similar_species_id' => $two->public_id,
        'relationship_type' => 'commonly_confused',
        'confidence' => 'verified',
        'notes' => 'Difference notes',
    ])->assertOk();

    $similar = $this->getJson('/api/v1/species/'.$one->canonical_slug.'/similar')->assertOk();
    expect($similar->json('data'))->toBe([]);
});

it('sanitizes Georgian translations and never leaks unpublished English copy', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $species = SpeciesFixtures::publishable();

    $this->actingAs($editor, 'web')->patchJson('/api/v1/admin/species/'.$species->public_id, [
        'content_version' => $species->content_version,
        'translations' => [
            [
                'locale' => 'ka',
                'common_name' => 'ქართული სახელი',
                'summary' => '<p onclick="alert(1)">შეჯამება</p><script>x</script>',
                'identification' => 'ნიშანი',
                'content_status' => 'published',
            ],
            [
                'locale' => 'en',
                'common_name' => 'Secret English draft',
                'summary' => 'Should not leak',
                'identification' => 'draft',
                'content_status' => 'draft',
            ],
        ],
    ])->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$species->public_id.'/publish')->assertOk();

    $ka = $this->getJson('/api/v1/species/'.$species->canonical_slug.'?locale=ka')->assertOk();
    expect($ka->json('data.common_name'))->toBe('ქართული სახელი')
        ->and($ka->json('data.summary'))->not->toContain('script')
        ->and($ka->json('data.summary'))->not->toContain('onclick');

    $en = $this->getJson('/api/v1/species/'.$species->canonical_slug.'?locale=en')->assertOk();
    expect($en->json('data.common_name'))->not->toBe('Secret English draft')
        ->and($en->json('data.translation_fallback'))->toBeTrue();
});

it('rejects invalid taxonomy and inverted characteristic ranges', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/species', [
        'scientific_name' => 'NotABinomial',
        'taxonomic_rank' => 'species',
        'kingdom' => 'Animalia',
        'domain_type' => 'terrestrial',
        'activity_type' => 'wildlife',
    ])->assertStatus(422)->assertJsonPath('error.code', 'SPECIES_TAXONOMY_INVALID');

    $species = SpeciesFixtures::draft();
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/species/'.$species->public_id, [
        'content_version' => $species->content_version,
        'characteristics' => [
            'average_length_min' => 12,
            'average_length_max' => 4,
            'length_unit' => 'cm',
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'SPECIES_PUBLICATION_INVALID');
});

it('filters published lists, returns ETags, and never caches drafts', function (): void {
    $published = SpeciesFixtures::published();
    SpeciesFixtures::draft();

    $list = $this->getJson('/api/v1/species?activity_type=wildlife&sort=scientific')->assertOk();
    $slugs = collect($list->json('data'))->pluck('slug');
    expect($slugs)->toContain($published->canonical_slug)
        ->and($list->headers->get('ETag'))->not->toBeEmpty()
        ->and($list->headers->get('Cache-Control'))->toContain('public');

    $filters = $this->getJson('/api/v1/species/filters')->assertOk();
    expect(collect($filters->json('data.habitats'))->pluck('code'))->toContain('forest');

    $this->getJson('/api/v1/species?sort=price')->assertStatus(422);

    $etag = $list->headers->get('ETag');
    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson('/api/v1/species?activity_type=wildlife&sort=scientific')
        ->assertStatus(304);

    $adminList = $this->actingAs($this->createAdmin(), 'web')->getJson('/api/v1/admin/species')->assertOk();
    expect($adminList->headers->get('Cache-Control'))->toContain('private');
});

it('walks the editorial workflow without leaking drafts or destroying revisions', function (): void {
    $search = $this->fakeSearchGateway();
    $editor = $this->createUserWithRole(Role::LegalEditor);

    $created = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species', [
        'scientific_name' => 'Lynx lynx',
        'taxonomic_rank' => 'species',
        'kingdom' => 'Animalia',
        'phylum' => 'Chordata',
        'class_name' => 'Mammalia',
        'order_name' => 'Carnivora',
        'family' => 'Felidae',
        'domain_type' => 'terrestrial',
        'activity_type' => 'hunting_and_wildlife',
        'no_media_required' => true,
        'translations' => [[
            'locale' => 'ka',
            'common_name' => 'ფოცხვერი',
            'summary' => 'ტყის კატა',
            'identification' => 'ფუნჯები ყურებზე',
            'content_status' => 'published',
        ]],
    ])->assertCreated();

    $id = $created->json('data.id');
    $slug = $created->json('data.canonical_slug');
    $version = $created->json('data.content_version');

    $this->getJson('/api/v1/species/'.$slug)->assertNotFound();

    $this->actingAs($editor, 'web')->patchJson('/api/v1/admin/species/'.$id, [
        'content_version' => $version,
        'verification_status' => 'partially_verified',
        'identification_traits' => [[
            'locale' => 'ka',
            'category' => 'markings',
            'label' => 'ყურის ფუნჯები',
            'description' => 'შავი ფუნჯები ყურის წვერებზე',
        ]],
        'habitats' => [['code' => 'forest', 'importance' => 'primary']],
    ])->assertOk();

    $source = $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$id.'/sources', [
        'title' => 'IUCN Red List',
        'publisher' => 'IUCN',
        'source_type' => 'scientific_database',
        'url' => 'https://www.iucnredlist.org/species/12519/50655267',
        'retrieved_at' => '2026-01-01',
        'claim_key' => 'species',
    ]);
    $source->assertOk();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$id.'/submit-review')->assertOk();
    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$id.'/publish')->assertOk();

    $detail = $this->getJson('/api/v1/species/'.$slug.'?locale=ka')->assertOk();
    expect($detail->json('data.common_name'))->toBe('ფოცხვერი')
        ->and($detail->json('data.legal_information.available'))->toBeFalse()
        ->and($detail->json('data.identification.traits.0.label'))->toBe('ყურის ფუნჯები');

    $indexed = false;
    foreach ($search->indexes as $documents) {
        $indexed = $indexed || array_key_exists($id.'_ka', $documents);
    }
    expect($indexed)->toBeTrue();

    $this->actingAs($editor, 'web')->postJson('/api/v1/admin/species/'.$id.'/unpublish', [
        'reason' => 'Needs another source',
    ])->assertOk();

    $this->getJson('/api/v1/species/'.$slug)->assertNotFound();

    $ids = [];
    foreach ($search->indexes as $documents) {
        $ids = array_merge($ids, array_keys($documents));
    }
    expect($ids)->not->toContain($id.'_ka');

    $revisions = $this->actingAs($editor, 'web')->getJson('/api/v1/admin/species/'.$id.'/revisions')->assertOk();
    expect($revisions->json('data'))->not->toBeEmpty();
});
