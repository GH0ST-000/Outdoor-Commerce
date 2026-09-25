<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 25: versioned official legal sources, provisions, structured rules.
 * Biological species facts remain on hunting tables. No invented regulations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_authorities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('authority_type', 32);
            $table->char('country_code', 2)->default('GE');
            $table->string('jurisdiction_code', 16)->default('GE');
            $table->string('official_website_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fictional')->default(false);
            $table->timestamps();
            $table->index(['authority_type', 'is_active'], 'legal_auth_type_active_idx');
        });

        Schema::create('legal_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_authority_id')->constrained('legal_authorities')->restrictOnDelete();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->string('source_type', 32);
            $table->string('official_base_url', 2048)->nullable();
            $table->string('allowed_domain', 191)->nullable();
            $table->string('language_code', 8)->default('ka');
            $table->string('jurisdiction_code', 16)->default('GE');
            $table->string('trust_level', 32)->default('unverified');
            $table->string('verification_status', 32)->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('monitor_for_changes')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_change_detected_at')->nullable();
            $table->string('last_etag', 255)->nullable();
            $table->string('last_modified_header', 255)->nullable();
            $table->unsignedBigInteger('last_content_length')->nullable();
            $table->char('last_checksum', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['verification_status', 'is_active'], 'legal_src_verify_active_idx');
            $table->index('jurisdiction_code');
        });

        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_source_id')->constrained('legal_sources')->restrictOnDelete();
            $table->foreignId('legal_authority_id')->constrained('legal_authorities')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 191)->unique();
            $table->string('official_identifier', 191)->nullable();
            $table->string('document_type', 32);
            $table->string('jurisdiction_code', 16)->default('GE');
            $table->string('language_code', 8)->default('ka');
            $table->string('official_url', 2048)->nullable();
            $table->date('publication_date')->nullable();
            $table->date('original_effective_date')->nullable();
            $table->timestamp('repealed_at')->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('official_identifier', 'legal_doc_official_id_idx');
            $table->index(['jurisdiction_code', 'status'], 'legal_doc_jur_status_idx');
        });

        Schema::create('legal_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->restrictOnDelete();
            $table->string('version_label', 64);
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('source_published_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->foreignId('retrieved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('content_checksum', 64);
            $table->string('checksum_algorithm', 16)->default('sha256');
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('extraction_status', 32)->default('not_requested');
            $table->string('verification_status', 32)->default('unverified');
            $table->string('review_status', 32)->default('draft');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 500)->nullable();
            $table->unsignedBigInteger('supersedes_version_id')->nullable();
            $table->unsignedInteger('content_version')->default(1);
            $table->timestamps();
            $table->unique(['legal_document_id', 'content_checksum'], 'legal_ver_doc_checksum_uid');
            $table->index(['effective_from', 'effective_until'], 'legal_ver_effective_idx');
            $table->index('review_status');
            $table->foreign('supersedes_version_id', 'legal_ver_supersedes_fk')
                ->references('id')->on('legal_document_versions')->nullOnDelete();
        });

        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'legal_doc_current_ver_fk')
                ->references('id')->on('legal_document_versions')->nullOnDelete();
        });

        Schema::create('legal_provisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->restrictOnDelete();
            $table->unsignedBigInteger('parent_provision_id')->nullable();
            $table->string('provision_type', 32);
            $table->string('reference_code', 128);
            $table->string('heading', 255)->nullable();
            $table->longText('official_text');
            $table->text('normalized_summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('review_status', 32)->default('draft');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['legal_document_version_id', 'reference_code'], 'legal_prov_ver_ref_idx');
            $table->foreign('parent_provision_id', 'legal_prov_parent_fk')
                ->references('id')->on('legal_provisions')->nullOnDelete();
        });

        Schema::create('legal_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title', 255);
            $table->string('slug', 191)->unique();
            $table->string('activity_type', 32);
            $table->string('rule_type', 32);
            $table->string('effect', 32);
            $table->unsignedBigInteger('species_id')->nullable();
            $table->string('jurisdiction_code', 16)->default('GE');
            $table->string('region_code', 64)->nullable();
            $table->string('zone_reference', 128)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 32)->default('draft');
            $table->string('verification_level', 32)->default('unverified');
            $table->text('interpretation_summary')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('supersedes_rule_id')->nullable();
            $table->unsignedInteger('content_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['activity_type', 'jurisdiction_code', 'status'], 'legal_rule_act_jur_st_idx');
            $table->index(['species_id', 'status'], 'legal_rule_species_st_idx');
            $table->index(['effect', 'status'], 'legal_rule_effect_st_idx');
            $table->index(['effective_from', 'effective_until'], 'legal_rule_effective_idx');
            $table->foreign('species_id', 'legal_rule_species_fk')->references('id')->on('species')->nullOnDelete();
            $table->foreign('supersedes_rule_id', 'legal_rule_supersedes_fk')
                ->references('id')->on('legal_rules')->nullOnDelete();
        });

        Schema::create('legal_rule_citations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->cascadeOnDelete();
            $table->foreignId('legal_provision_id')->constrained('legal_provisions')->restrictOnDelete();
            $table->string('citation_purpose', 32);
            $table->string('quoted_excerpt', 400)->nullable();
            $table->string('citation_note', 500)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['legal_rule_id', 'legal_provision_id'], 'legal_cite_rule_prov_uid');
        });

        Schema::create('legal_rule_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->cascadeOnDelete();
            $table->string('condition_type', 32);
            $table->string('operator', 32);
            $table->string('value_type', 32);
            $table->string('string_value', 255)->nullable();
            $table->integer('integer_value')->nullable();
            $table->decimal('decimal_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->date('date_value')->nullable();
            $table->time('time_value')->nullable();
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('unit_code', 16)->nullable();
            $table->string('group_key', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['condition_type', 'reference_type'], 'legal_cond_type_ref_idx');
        });

        Schema::create('legal_rule_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->cascadeOnDelete();
            $table->string('limit_type', 32);
            $table->decimal('amount', 12, 4)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('period', 32)->default('not_applicable');
            $table->decimal('minimum_value', 12, 4)->nullable();
            $table->decimal('maximum_value', 12, 4)->nullable();
            $table->string('measurement_unit', 32)->nullable();
            $table->string('applies_per', 32)->default('person');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('legal_rule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('base_rule_id');
            $table->unsignedBigInteger('exception_rule_id');
            $table->string('relationship_type', 32);
            $table->unsignedInteger('precedence')->default(100);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['base_rule_id', 'exception_rule_id', 'relationship_type'], 'legal_exc_pair_uid');
            $table->foreign('base_rule_id', 'legal_exc_base_fk')->references('id')->on('legal_rules')->cascadeOnDelete();
            $table->foreign('exception_rule_id', 'legal_exc_exc_fk')->references('id')->on('legal_rules')->cascadeOnDelete();
        });

        Schema::create('legal_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('reviewable_type', 64);
            $table->unsignedBigInteger('reviewable_id');
            $table->string('review_type', 32);
            $table->string('decision', 32);
            $table->text('comments')->nullable();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->index(['reviewable_type', 'reviewable_id'], 'legal_reviews_morph_idx');
        });

        Schema::create('legal_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('first_rule_id');
            $table->unsignedBigInteger('second_rule_id');
            $table->string('conflict_type', 32);
            $table->string('severity', 16)->default('medium');
            $table->string('status', 32)->default('open');
            $table->timestamp('detected_at');
            $table->string('detected_by', 32)->default('system');
            $table->text('evidence')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity'], 'legal_conf_status_sev_idx');
            $table->foreign('first_rule_id', 'legal_conf_first_fk')->references('id')->on('legal_rules')->cascadeOnDelete();
            $table->foreign('second_rule_id', 'legal_conf_second_fk')->references('id')->on('legal_rules')->cascadeOnDelete();
        });

        Schema::create('legal_change_detections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_source_id')->constrained('legal_sources')->cascadeOnDelete();
            $table->unsignedBigInteger('legal_document_version_id')->nullable();
            $table->string('status', 32)->default('open');
            $table->json('previous_metadata')->nullable();
            $table->json('current_metadata')->nullable();
            $table->string('signal', 64);
            $table->text('internal_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['legal_source_id', 'status'], 'legal_chg_src_status_idx');
            $table->foreign('legal_document_version_id', 'legal_chg_version_fk')
                ->references('id')->on('legal_document_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_change_detections');
        Schema::dropIfExists('legal_conflicts');
        Schema::dropIfExists('legal_reviews');
        Schema::dropIfExists('legal_rule_exceptions');
        Schema::dropIfExists('legal_rule_limits');
        Schema::dropIfExists('legal_rule_conditions');
        Schema::dropIfExists('legal_rule_citations');
        Schema::dropIfExists('legal_rules');
        Schema::dropIfExists('legal_provisions');
        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->dropForeign('legal_doc_current_ver_fk');
        });
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('legal_sources');
        Schema::dropIfExists('legal_authorities');
    }
};
