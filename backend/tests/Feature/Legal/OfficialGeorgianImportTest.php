<?php

declare(strict_types=1);

use App\Domains\Geography\Services\CanonicalBufferGuard;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Hunting\Support\ScientificNameNormalizer;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Exceptions\OfficialSnapshotException;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCitation;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Services\LegalPublicationValidator;
use App\Domains\Legal\Services\OfficialGeorgianSnapshotImporter;
use App\Domains\Legal\Services\SeasonOccurrenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('rejects a snapshot whose checksum does not match the manifest', function (): void {
    $root = sys_get_temp_dir().'/ge-snapshot-'.Str::uuid();
    File::copyDirectory(base_path('database/data/official/georgia'), $root);
    File::append($root.'/hunting/daily_limits.json', ' ');

    expect(fn () => app(OfficialGeorgianSnapshotImporter::class)->verifyChecksums($root))
        ->toThrow(OfficialSnapshotException::class, 'checksum mismatch');

    File::deleteDirectory($root);
});

it('aborts when a current species scientific name is not a binomial', function (): void {
    $root = sys_get_temp_dir().'/ge-snapshot-'.Str::uuid();
    File::copyDirectory(base_path('database/data/official/georgia'), $root);
    $relative = 'hunting/migratory_bird_rules.json';
    $path = $root.'/'.$relative;
    File::put($path, str_replace('Anas crecca', 'bad name', File::get($path)));
    $manifestPath = $root.'/manifest.json';
    $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest['files'] as &$file) {
        if ($file['path'] === $relative) {
            $file['sha256'] = hash_file('sha256', $path);
        }
    }
    unset($file);
    File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

    expect(fn () => app(OfficialGeorgianSnapshotImporter::class)->import($root))
        ->toThrow(OfficialSnapshotException::class, 'Scientific name');

    expect(Species::query()->count())->toBe(0);
    File::deleteDirectory($root);
});

it('imports official georgian hunting data as unreviewed drafts and does not publish conflicts', function (): void {
    $importer = app(OfficialGeorgianSnapshotImporter::class);
    $first = $importer->import();

    expect($first['species'])->toBe(19)
        ->and($first['seasons'])->toBe(25)
        ->and($first['limits'])->toBe(31)
        ->and($first['conflicts'])->toBe(2)
        ->and($first['failed'])->toBe(0)
        ->and(LegalRule::query()->where('status', LegalRuleStatus::Published)->count())->toBe(0)
        ->and(LegalSeasonOccurrence::query()->count())->toBe(0)
        ->and(LegalRule::query()->where('activity_type', LegalActivityType::Fishing)->count())->toBe(0);

    $mallard = Species::query()->where('scientific_name', 'Anas platyrhynchos')->firstOrFail();
    expect($mallard->translations()->where('locale', 'ka')->value('common_name'))->toBe('გარეული იხვი');
    $crecca = Species::query()->where('scientific_name_normalized', ScientificNameNormalizer::normalize('Anas crecca'))->firstOrFail();
    expect(SpeciesAlias::query()->where('species_id', $crecca->id)->where('name', 'სტვენია იხვი')->exists())->toBeTrue();

    $crossYear = LegalSeasonDefinition::query()
        ->where('species_id', $mallard->id)
        ->where('start_month', 11)
        ->where('start_day', 1)
        ->where('end_month', 3)
        ->where('end_day', 1)
        ->where('crosses_calendar_year', true)
        ->firstOrFail();
    expect($crossYear->status)->toBe(LegalRuleStatus::InReview);
    $preview = app(SeasonOccurrenceGenerator::class)->generate($crossYear, 2026, 2026, false);
    expect($preview['preview'][0]['local_start_date'])->toBe('2026-11-01')
        ->and($preview['preview'][0]['local_end_date_inclusive'])->toStartWith('2027-');

    $quail = Species::query()->where('scientific_name', 'Coturnix coturnix')->firstOrFail();
    expect(LegalSeasonDefinition::query()->where('species_id', $quail->id)->exists())->toBeFalse();
    $opening = LegalRule::query()->where('slug', 'ge-o95-art-3-2-opening-window')->firstOrFail();
    $annual = LegalRule::query()->where('slug', 'ge-mepa-26537-2026-2027-window')->firstOrFail();
    expect(LegalSeasonDefinition::query()->where('legal_rule_id', $annual->id)->exists())->toBeFalse();
    $dates = $annual->conditions()->pluck('date_value')->map(fn (mixed $date): string => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (string) $date)->all();
    expect($dates)->toContain('2026-08-22', '2027-03-01');
    $conflict = LegalConflict::query()
        ->whereIn('first_rule_id', [$opening->id, $annual->id])
        ->whereIn('second_rule_id', [$opening->id, $annual->id])
        ->firstOrFail();
    expect($conflict->isBlocking())->toBeTrue();
    expect(fn () => app(LegalPublicationValidator::class)->assertCanPublish($opening, User::factory()->create()))
        ->toThrow(LegalException::class);

    $national = LegalRule::query()->where('slug', 'ge-o95-anas-platyrhynchos-except-four')->firstOrFail();
    expect($national->limits()->where('amount', 6)->where('unit', 'ცალი')->exists())->toBeTrue();
    expect($national->conditions()->where('string_value', 'დმანისი')->exists())->toBeTrue();
    expect(LegalRuleCitation::query()->where('legal_rule_id', $national->id)->exists())->toBeTrue();
    expect(LegalDocumentVersion::query()->where('version_label', 'consolidated-2024-08-07')->count())->toBe(1);
    expect(LegalDocumentVersion::query()->where('version_label', 'original-form-2014-01-10-not-current')->exists())->toBeTrue();

    $buffer = app(CanonicalBufferGuard::class)->plan(500, 'order-95:article-3:paragraph-7');
    expect($buffer['generated'])->toBeFalse();

    $second = $importer->import();
    expect($second['created'])->toBe(0)
        ->and(Species::query()->count())->toBe(19)
        ->and(LegalConflict::query()->count())->toBe(2);
});

it('does not overwrite a reviewed species that already uses the scientific name', function (): void {
    $species = Species::factory()->published()->create([
        'scientific_name' => 'Anas platyrhynchos',
        'scientific_name_normalized' => ScientificNameNormalizer::normalize('Anas platyrhynchos'),
        'genus' => 'Anas',
        'species_epithet' => 'platyrhynchos',
        'canonical_slug' => 'human-mallard',
    ]);
    SpeciesTranslation::query()->create([
        'species_id' => $species->id,
        'locale' => 'ka',
        'common_name' => 'Human Name',
        'summary' => 'Reviewed biological summary.',
        'identification' => 'Reviewed identification.',
        'content_status' => 'draft',
    ]);

    app(OfficialGeorgianSnapshotImporter::class)->import();

    expect($species->fresh()->translations()->where('locale', 'ka')->value('common_name'))->toBe('Human Name')
        ->and($species->fresh()->publication_status)->toBe(SpeciesPublicationStatus::Published)
        ->and(LegalRule::query()->where('slug', 'ge-o95-anas-platyrhynchos-except-four')->value('species_id'))->toBe($species->id);
});

it('keeps unpublished seasons unknown and shows a published calendar season with its citation', function (): void {
    app(OfficialGeorgianSnapshotImporter::class)->import();

    $this->getJson('/api/v1/outdoor/availability?activity=fishing&from=2026-05-01&to=2026-05-07')
        ->assertOk()
        ->assertJsonPath('data.results', [])
        ->assertJsonPath('data.disclaimer', config('legal.evaluation.disclaimer_key'));

    $woodcock = Species::query()->where('scientific_name', 'Scolopax rusticola')->firstOrFail();
    $woodcock->forceFill([
        'publication_status' => SpeciesPublicationStatus::Published,
        'published_at' => now(),
    ])->save();
    $definition = LegalSeasonDefinition::query()->where('species_id', $woodcock->id)->firstOrFail();
    $definition->rule?->forceFill([
        'status' => LegalRuleStatus::Published,
        'published_at' => now(),
        'reviewed_at' => now(),
    ])->save();
    $definition->forceFill([
        'status' => LegalRuleStatus::Published,
        'published_at' => now(),
        'reviewed_at' => now(),
    ])->save();
    app(SeasonOccurrenceGenerator::class)->generate($definition->fresh(), 2026, 2026);

    $this->getJson('/api/v1/species/'.$woodcock->canonical_slug.'/seasons?from=2026-09-01&to=2026-09-07')
        ->assertOk()
        ->assertJsonPath('data.availability.overall_state', 'unknown');

    $open = $this->getJson('/api/v1/outdoor/availability?activity=hunting&from=2026-10-20&to=2026-10-25&species='.$woodcock->canonical_slug)
        ->assertOk();
    expect($open->json('data.results.0.citations'))->not->toBeEmpty()
        ->and($open->json('data.results.0.limits.0.unit'))->toBe('ცალი')
        ->and($open->json('data.results.0.limits.0.amount'))->toEqual(7);
});
