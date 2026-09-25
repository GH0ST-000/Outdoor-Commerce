<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 24: bilingual species knowledge base. Biological facts only — no seasons,
 * limits, permits, or hunting/fishing permission fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title', 255);
            $table->string('publisher', 255);
            $table->string('source_type', 32);
            $table->string('url', 2048)->nullable();
            $table->string('document_identifier', 191)->nullable();
            $table->string('language', 8)->nullable();
            $table->date('published_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->date('retrieved_at')->nullable();
            $table->boolean('is_official')->default(false);
            $table->string('verification_status', 32)->default('unverified');
            $table->string('checksum', 64)->nullable();
            $table->string('archived_local_path', 512)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'is_official']);
            $table->index('verification_status');
        });

        Schema::create('species', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('canonical_slug', 191)->unique();
            $table->string('scientific_name', 191);
            $table->string('scientific_name_normalized', 191);
            $table->string('scientific_name_authorship', 191)->nullable();
            $table->string('taxonomic_rank', 32);
            $table->string('kingdom', 64);
            $table->string('phylum', 64)->nullable();
            $table->string('class_name', 64)->nullable();
            $table->string('order_name', 64)->nullable();
            $table->string('family', 64)->nullable();
            $table->string('genus', 64)->nullable();
            $table->string('species_epithet', 64)->nullable();
            $table->string('domain_type', 32);
            $table->string('activity_type', 32);
            $table->string('native_status', 32)->nullable();
            $table->string('verification_status', 32)->default('unverified');
            $table->string('publication_status', 32)->default('draft');
            $table->unsignedInteger('content_version')->default(1);
            $table->boolean('no_media_required')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('scientific_name_normalized');
            $table->index(['publication_status', 'verification_status']);
            $table->index(['activity_type', 'domain_type']);
            $table->index(['kingdom', 'class_name', 'order_name', 'family']);
            $table->index('genus');
        });

        Schema::create('species_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('common_name', 120);
            $table->string('short_name', 80)->nullable();
            $table->text('summary');
            $table->text('identification');
            $table->text('appearance')->nullable();
            $table->text('behavior')->nullable();
            $table->text('diet')->nullable();
            $table->text('habitat_description')->nullable();
            $table->text('breeding_notes')->nullable();
            $table->text('seasonal_behavior')->nullable();
            $table->text('field_notes')->nullable();
            $table->text('safety_notes')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->string('content_status', 16)->default('draft');
            $table->timestamps();

            $table->unique(['species_id', 'locale']);
            $table->index(['locale', 'content_status']);
        });

        Schema::create('species_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('locale', 8)->nullable();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->string('type', 32);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_public')->default(false);
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->timestamps();

            $table->unique(['species_id', 'normalized_name', 'locale'], 'species_aliases_unique_name');
            $table->index(['normalized_name', 'is_searchable']);
            $table->index(['species_id', 'is_public']);
        });

        Schema::create('species_characteristics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->unique()->constrained('species')->cascadeOnDelete();
            $table->decimal('average_length_min', 10, 2)->nullable();
            $table->decimal('average_length_max', 10, 2)->nullable();
            $table->string('length_unit', 8)->nullable();
            $table->decimal('average_weight_min', 10, 2)->nullable();
            $table->decimal('average_weight_max', 10, 2)->nullable();
            $table->string('weight_unit', 8)->nullable();
            $table->decimal('lifespan_years_min', 6, 2)->nullable();
            $table->decimal('lifespan_years_max', 6, 2)->nullable();
            $table->string('activity_pattern', 32)->nullable();
            $table->string('social_behavior', 32)->nullable();
            $table->string('migration_pattern', 32)->nullable();
            $table->string('water_type', 32)->nullable();
            $table->decimal('preferred_depth_min', 10, 2)->nullable();
            $table->decimal('preferred_depth_max', 10, 2)->nullable();
            $table->string('depth_unit', 8)->nullable();
            $table->decimal('temperature_range_min', 6, 2)->nullable();
            $table->decimal('temperature_range_max', 6, 2)->nullable();
            $table->string('temperature_unit', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('habitats', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('habitat_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('habitat_id')->constrained('habitats')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['habitat_id', 'locale']);
        });

        Schema::create('species_habitats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->foreignId('habitat_id')->constrained('habitats')->restrictOnDelete();
            $table->string('importance', 32)->default('secondary');
            $table->string('notes', 500)->nullable();
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->timestamps();

            $table->unique(['species_id', 'habitat_id']);
            $table->index('habitat_id');
        });

        Schema::create('species_identification_traits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('category', 32);
            $table->string('label', 120);
            $table->text('description');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->timestamps();

            $table->index(['species_id', 'locale', 'sort_order']);
        });

        Schema::create('species_similar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->foreignId('similar_species_id')->constrained('species')->cascadeOnDelete();
            $table->string('relationship_type', 32);
            $table->string('confidence', 16)->default('unverified');
            $table->text('notes')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->timestamps();

            $table->unique(['species_id', 'similar_species_id', 'relationship_type'], 'species_similar_unique');
            $table->index('similar_species_id');
        });

        Schema::create('species_conservation_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('assessment_system', 64);
            $table->string('status_code', 64);
            $table->string('assessment_scope', 32);
            $table->date('assessed_at')->nullable();
            $table->foreignId('source_id')->constrained('knowledge_sources')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['species_id', 'assessment_system', 'assessment_scope'], 'species_conservation_unique');
            $table->index(['assessment_scope', 'status_code'], 'species_cons_scope_status_idx');
        });

        Schema::create('knowledge_citations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('knowledge_sources')->restrictOnDelete();
            $table->string('citable_type', 64);
            $table->unsignedBigInteger('citable_id');
            $table->string('claim_key', 64)->nullable();
            $table->string('page_reference', 64)->nullable();
            $table->string('section_reference', 128)->nullable();
            $table->string('quotation_excerpt', 280)->nullable();
            $table->string('editor_note', 500)->nullable();
            $table->timestamps();

            $table->index(['citable_type', 'citable_id']);
            $table->index('source_id');
        });

        Schema::create('species_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 240);
            $table->json('snapshot');
            $table->timestamp('created_at');

            $table->unique(['species_id', 'revision_number']);
            $table->index(['species_id', 'created_at']);
        });

        Schema::create('species_media_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_attachment_id')->unique()->constrained('media_attachments')->cascadeOnDelete();
            $table->string('photographer_or_creator', 191)->nullable();
            $table->string('license', 64)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('locale', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_media_attributions');
        Schema::dropIfExists('species_revisions');
        Schema::dropIfExists('knowledge_citations');
        Schema::dropIfExists('species_conservation_assessments');
        Schema::dropIfExists('species_similar');
        Schema::dropIfExists('species_identification_traits');
        Schema::dropIfExists('species_habitats');
        Schema::dropIfExists('habitat_translations');
        Schema::dropIfExists('habitats');
        Schema::dropIfExists('species_characteristics');
        Schema::dropIfExists('species_aliases');
        Schema::dropIfExists('species_translations');
        Schema::dropIfExists('species');
        Schema::dropIfExists('knowledge_sources');
    }
};
