<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Day 27: versioned spatial sources, datasets, zones, and legal-zone assignments.
 * Canonical geometry is stored as WKT (SRID 4326, longitude then latitude) on every
 * driver. MySQL additionally stores a native MULTIPOLYGON column with a spatial index
 * for MBR candidate filtering. PHP point-in-polygon remains the legal classifier.
 * No invented Georgian protected areas are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spatial_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_source_id')->nullable()->constrained('legal_sources')->nullOnDelete();
            $table->foreignId('legal_authority_id')->nullable()->constrained('legal_authorities')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source_type', 64);
            $table->string('official_url', 2048)->nullable();
            $table->string('official_identifier')->nullable();
            $table->string('publisher_name');
            $table->string('jurisdiction_code', 16);
            $table->string('license_name')->nullable();
            $table->string('license_url', 2048)->nullable();
            $table->text('attribution_text')->nullable();
            $table->text('allowed_usage_notes')->nullable();
            $table->string('verification_status', 32)->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fictional')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['verification_status', 'is_active'], 'ss_verify_active_idx');
            $table->index(['jurisdiction_code', 'source_type'], 'ss_jur_type_idx');
        });

        Schema::create('spatial_datasets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('spatial_source_id')->constrained('spatial_sources')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('dataset_type', 64);
            $table->string('jurisdiction_code', 16);
            $table->text('description')->nullable();
            $table->string('native_crs', 64)->nullable();
            $table->unsignedSmallInteger('canonical_srid')->default(4326);
            $table->string('update_frequency', 64)->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_fictional')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['spatial_source_id', 'slug'], 'sd_source_slug_uidx');
            $table->index(['status', 'dataset_type', 'jurisdiction_code'], 'sd_status_type_jur_idx');
        });

        Schema::create('spatial_dataset_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('spatial_dataset_id')->constrained('spatial_datasets')->restrictOnDelete();
            $table->string('version_label', 64);
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('source_published_at')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('retrieved_at');
            $table->foreignId('retrieved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_checksum', 64);
            $table->string('checksum_algorithm', 16)->default('sha256');
            $table->string('storage_disk', 64);
            $table->string('storage_path', 512);
            $table->string('original_filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('feature_count')->nullable();
            $table->string('source_crs', 64)->nullable();
            $table->json('property_mapping')->nullable();
            $table->string('import_status', 32)->default('pending');
            $table->string('review_status', 32)->default('draft');
            $table->json('validation_summary')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('supersedes_version_id')->nullable()->constrained('spatial_dataset_versions')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['spatial_dataset_id', 'version_label'], 'sdv_dataset_label_uidx');
            $table->unique(['spatial_dataset_id', 'content_checksum'], 'sdv_dataset_checksum_uidx');
            $table->index(['review_status', 'import_status'], 'sdv_review_import_idx');
            $table->index(['effective_from', 'effective_until'], 'sdv_effective_idx');
        });

        Schema::table('spatial_datasets', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('spatial_dataset_versions')
                ->nullOnDelete();
        });

        Schema::create('spatial_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('spatial_dataset_id')->constrained('spatial_datasets')->restrictOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('slug');
            $table->string('zone_type', 64);
            $table->string('jurisdiction_code', 16);
            $table->string('region_code', 64)->nullable();
            $table->string('default_name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->boolean('is_fictional')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['spatial_dataset_id', 'slug'], 'sz_dataset_slug_uidx');
            $table->unique(['spatial_dataset_id', 'external_identifier'], 'sz_dataset_ext_uidx');
            $table->index(['zone_type', 'status', 'jurisdiction_code'], 'sz_type_status_jur_idx');
            $table->index(['region_code', 'status'], 'sz_region_status_idx');
        });

        Schema::create('spatial_zone_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spatial_zone_id')->constrained('spatial_zones')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->timestamps();

            $table->unique(['spatial_zone_id', 'locale'], 'szt_zone_locale_uidx');
        });

        Schema::create('spatial_zone_geometry_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('spatial_zone_id')->constrained('spatial_zones')->restrictOnDelete();
            $table->foreignId('spatial_dataset_version_id')->constrained(
                table: 'spatial_dataset_versions',
                indexName: 'szgv_dataset_version_fk',
            )->restrictOnDelete();
            $table->longText('geometry_wkt');
            $table->decimal('min_longitude', 11, 8);
            $table->decimal('min_latitude', 10, 8);
            $table->decimal('max_longitude', 11, 8);
            $table->decimal('max_latitude', 10, 8);
            $table->decimal('centroid_longitude', 11, 8);
            $table->decimal('centroid_latitude', 10, 8);
            $table->unsignedInteger('vertex_count')->default(0);
            $table->unsignedInteger('polygon_count')->default(1);
            $table->unsignedBigInteger('area_square_meters')->nullable();
            $table->string('geometry_checksum', 64);
            $table->string('source_feature_identifier')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('validation_status', 32)->default('pending');
            $table->json('validation_warnings')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['spatial_zone_id', 'status'], 'szgv_zone_status_idx');
            $table->index(['status', 'effective_from', 'effective_until'], 'szgv_status_effective_idx');
            $table->index(['min_longitude', 'max_longitude', 'min_latitude', 'max_latitude'], 'szgv_bbox_idx');
            $table->index('geometry_checksum', 'szgv_checksum_idx');
            $table->unique(['spatial_zone_id', 'spatial_dataset_version_id'], 'szgv_zone_version_uidx');
        });

        Schema::create('legal_rule_spatial_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->restrictOnDelete();
            $table->foreignId('spatial_zone_id')->constrained('spatial_zones')->restrictOnDelete();
            $table->foreignId('zone_geometry_version_id')->nullable()->constrained('spatial_zone_geometry_versions')->nullOnDelete();
            $table->string('assignment_type', 32);
            $table->unsignedInteger('precedence')->default(0);
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['spatial_zone_id', 'status'], 'lrsz_zone_status_idx');
            $table->index(['legal_rule_id', 'status'], 'lrsz_rule_status_idx');
            $table->index(['status', 'effective_from', 'effective_until'], 'lrsz_status_effective_idx');
        });

        Schema::create('spatial_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('spatial_dataset_version_id')->constrained('spatial_dataset_versions')->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('features_discovered')->default(0);
            $table->unsignedInteger('features_validated')->default(0);
            $table->unsignedInteger('features_imported')->default(0);
            $table->unsignedInteger('features_rejected')->default(0);
            $table->unsignedInteger('warnings_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at'], 'si_status_started_idx');
        });

        Schema::create('spatial_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spatial_import_id')->constrained('spatial_imports')->cascadeOnDelete();
            $table->string('source_feature_identifier')->nullable();
            $table->string('error_code', 64);
            $table->string('message', 500);
            $table->string('context', 255)->nullable();
            $table->string('resolution_status', 32)->default('open');
            $table->timestamps();

            $table->index(['spatial_import_id', 'error_code'], 'sie_import_code_idx');
        });

        if ($this->isMysql()) {
            DB::statement("ALTER TABLE spatial_zone_geometry_versions ADD geometry GEOMETRY NOT NULL SRID 4326 DEFAULT (ST_GeomFromText('MULTIPOLYGON(((0 0, 0.000001 0, 0.000001 0.000001, 0 0.000001, 0 0)))', 4326))");
            DB::statement('ALTER TABLE spatial_zone_geometry_versions ADD SPATIAL INDEX szgv_geometry_sidx (geometry)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spatial_import_errors');
        Schema::dropIfExists('spatial_imports');
        Schema::dropIfExists('legal_rule_spatial_zones');
        Schema::dropIfExists('spatial_zone_geometry_versions');
        Schema::dropIfExists('spatial_zone_translations');
        Schema::dropIfExists('spatial_zones');
        Schema::table('spatial_datasets', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('spatial_dataset_versions');
        Schema::dropIfExists('spatial_datasets');
        Schema::dropIfExists('spatial_sources');
    }

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
