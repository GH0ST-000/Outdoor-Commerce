<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 26: source-backed season definitions, derived occurrences, overrides, generation runs.
 * Occurrences are projections. Definitions and Day 25 legal rules remain authoritative.
 * No invented hunting or fishing seasons are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_season_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->restrictOnDelete();
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->string('activity_type', 32);
            $table->string('season_type', 32);
            $table->string('schedule_type', 32);
            $table->string('jurisdiction_code', 16)->default('GE');
            $table->string('region_code', 64)->nullable();
            $table->string('zone_reference', 128)->nullable();
            $table->string('timezone', 64)->default('Asia/Tbilisi');
            $table->string('boundary_precision', 16)->default('date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('start_month')->nullable();
            $table->unsignedTinyInteger('start_day')->nullable();
            $table->unsignedTinyInteger('end_month')->nullable();
            $table->unsignedTinyInteger('end_day')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('first_season_year')->nullable();
            $table->unsignedSmallInteger('last_season_year')->nullable();
            $table->boolean('crosses_calendar_year')->default(false);
            $table->string('status', 32)->default('draft');
            $table->string('verification_level', 32)->default('unverified');
            $table->unsignedSmallInteger('generated_through_year')->nullable();
            $table->unsignedInteger('content_version')->default(1);
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'activity_type', 'jurisdiction_code'], 'lsd_status_activity_jur_idx');
            $table->index(['species_id', 'activity_type', 'status'], 'lsd_species_activity_status_idx');
            $table->index(['legal_rule_id', 'status'], 'lsd_rule_status_idx');
        });

        Schema::create('legal_season_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_definition_id')->constrained('legal_season_definitions')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->restrictOnDelete();
            $table->string('activity_type', 32);
            $table->unsignedSmallInteger('season_year');
            $table->string('jurisdiction_code', 16);
            $table->string('region_code', 64)->nullable();
            $table->string('zone_reference', 128)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at_exclusive');
            $table->date('local_start_date');
            $table->date('local_end_date_inclusive');
            $table->string('effect', 32);
            $table->string('generation_version', 64);
            $table->timestamp('definition_updated_at');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['season_definition_id', 'season_year'], 'lso_definition_year_uidx');
            $table->index(['activity_type', 'jurisdiction_code', 'is_current', 'starts_at', 'ends_at_exclusive'], 'lso_overlap_idx');
            $table->index(['species_id', 'activity_type', 'is_current', 'starts_at'], 'lso_species_overlap_idx');
            $table->index(['season_year', 'is_current'], 'lso_year_current_idx');
            $table->index(['region_code', 'is_current'], 'lso_region_current_idx');
        });

        Schema::create('legal_season_overrides', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('base_season_definition_id')->constrained('legal_season_definitions')->restrictOnDelete();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->restrictOnDelete();
            $table->string('override_type', 32);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at_exclusive');
            $table->string('jurisdiction_code', 16);
            $table->string('region_code', 64)->nullable();
            $table->string('zone_reference', 128)->nullable();
            $table->string('reason', 500);
            $table->unsignedInteger('precedence')->default(0);
            $table->string('status', 32)->default('draft');
            $table->string('verification_level', 32)->default('unverified');
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['base_season_definition_id', 'status'], 'lsover_base_status_idx');
            $table->index(['status', 'starts_at', 'ends_at_exclusive'], 'lsover_status_interval_idx');
            $table->index(['legal_rule_id', 'status'], 'lsover_rule_status_idx');
        });

        Schema::create('legal_calendar_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('from_year');
            $table->unsignedSmallInteger('through_year');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('definitions_processed')->default(0);
            $table->unsignedInteger('occurrences_created')->default(0);
            $table->unsignedInteger('occurrences_updated')->default(0);
            $table->unsignedInteger('occurrences_invalidated')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->string('triggered_by_type', 32)->default('system');
            $table->unsignedBigInteger('triggered_by_id')->nullable();
            $table->string('error_summary', 500)->nullable();
            $table->string('jurisdiction_code', 16)->nullable();
            $table->boolean('dry_run')->default(false);
            $table->timestamps();

            $table->index(['status', 'started_at'], 'lcgr_status_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_calendar_generation_runs');
        Schema::dropIfExists('legal_season_overrides');
        Schema::dropIfExists('legal_season_occurrences');
        Schema::dropIfExists('legal_season_definitions');
    }
};
