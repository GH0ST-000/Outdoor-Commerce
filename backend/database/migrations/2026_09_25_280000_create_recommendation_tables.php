<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Day 30: controlled context taxonomy, product assignments, typed compatibility
 * rules, versioned ranking profiles, and bounded merchandising.
 * Catalog categories and attributes stay authoritative for the storefront.
 * This taxonomy only maps products to outdoor-context codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_taxonomy_terms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('dimension', 64);
            $table->string('code', 64);
            $table->foreignId('parent_id')->nullable()->constrained('context_taxonomy_terms')->nullOnDelete();
            $table->string('default_label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('catalog_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('catalog_attribute_id')->nullable()->constrained('attributes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dimension', 'code'], 'ctx_term_dim_code_uidx');
            $table->index(['dimension', 'is_active', 'sort_order'], 'ctx_term_dim_active_idx');
        });

        Schema::create('context_taxonomy_term_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_taxonomy_term_id')->constrained(
                table: 'context_taxonomy_terms',
                indexName: 'ctx_term_tr_term_fk',
            )->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['context_taxonomy_term_id', 'locale'], 'ctx_term_tr_uidx');
        });

        Schema::create('product_context_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_key')->default(0);
            $table->foreignId('context_taxonomy_term_id')->constrained('context_taxonomy_terms')->restrictOnDelete();
            $table->string('assignment_type', 32);
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('source_type', 64);
            $table->string('source_reference_type')->nullable();
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'variant_key', 'context_taxonomy_term_id', 'assignment_type'], 'pca_dedupe_uidx');
            $table->index(['status', 'context_taxonomy_term_id', 'effective_from'], 'pca_status_term_idx');
            $table->index(['product_id', 'status'], 'pca_product_status_idx');
        });

        Schema::create('product_compatibility_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('rule_type', 32);
            $table->string('context_dimension', 64);
            $table->string('operator', 32);
            $table->foreignId('context_term_id')->nullable()->constrained('context_taxonomy_terms')->nullOnDelete();
            $table->string('value_type', 32);
            $table->string('string_value')->nullable();
            $table->integer('integer_value')->nullable();
            $table->decimal('decimal_value', 12, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('unit_code', 32)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedSmallInteger('weight')->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'product_id', 'product_category_id'], 'pcr_status_target_idx');
        });

        Schema::create('recommendation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('placement', 64);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 32)->default('draft');
            $table->unsignedSmallInteger('minimum_score')->default(35);
            $table->unsignedSmallInteger('maximum_results')->default(8);
            $table->unsignedSmallInteger('candidate_limit')->default(80);
            $table->json('configuration');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['placement', 'version'], 'rec_profile_place_ver_uidx');
            $table->unique('slug');
            $table->index(['placement', 'status', 'effective_from'], 'rec_profile_place_status_idx');
        });

        Schema::create('recommendation_profile_weights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recommendation_profile_id')->constrained('recommendation_profiles')->cascadeOnDelete();
            $table->string('dimension', 64);
            $table->unsignedSmallInteger('weight');
            $table->timestamps();
            $table->unique(['recommendation_profile_id', 'dimension'], 'rec_weight_dim_uidx');
        });

        Schema::create('recommendation_merchandising_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('placement', 64);
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('adjustment_type', 32);
            $table->integer('adjustment_value')->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->string('status', 32)->default('draft');
            $table->boolean('paid_placement')->default(false);
            $table->string('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['placement', 'status', 'starts_at', 'ends_at'], 'rec_merch_window_idx');
        });

        Schema::create('recommendation_simulations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('recommendation_profile_id')->nullable()->constrained('recommendation_profiles')->nullOnDelete();
            $table->json('safe_context_snapshot');
            $table->json('result_summary');
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at');
            $table->timestamps();
        });

        $this->seedVocabulary();
        $this->seedProfiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_simulations');
        Schema::dropIfExists('recommendation_merchandising_rules');
        Schema::dropIfExists('recommendation_profile_weights');
        Schema::dropIfExists('recommendation_profiles');
        Schema::dropIfExists('product_compatibility_rules');
        Schema::dropIfExists('product_context_assignments');
        Schema::dropIfExists('context_taxonomy_term_translations');
        Schema::dropIfExists('context_taxonomy_terms');
    }

    private function seedVocabulary(): void
    {
        $terms = [
            ['activity', 'hunting', 'Hunting', 'ნადირობა'],
            ['activity', 'fishing', 'Fishing', 'თევზაობა'],
            ['species_category', 'game_bird', 'Game birds', 'სანადირო ფრინველი'],
            ['species_category', 'ungulate', 'Ungulates', 'ჩლიქოსნები'],
            ['species_category', 'freshwater_fish', 'Freshwater fish', 'მტკნარი წყლის თევზი'],
            ['method', 'firearm', 'Firearm', 'ცეცხლსასროლი'],
            ['method', 'archery', 'Archery', 'მშვილდოსნობა'],
            ['method', 'spinning', 'Spinning', 'სპინინგი'],
            ['equipment', 'hunting_optics', 'Hunting optics', 'სანადირო ოპტიკა'],
            ['equipment', 'ammunition', 'Ammunition', 'საბრძოლო მასალა'],
            ['equipment', 'fishing_rod', 'Fishing rod', 'სათევზაო ანკესი'],
            ['season_phase', 'open', 'Open season', 'ღია სეზონი'],
            ['season_phase', 'closed', 'Closed season', 'დახურული სეზონი'],
            ['region', 'ge', 'Georgia', 'საქართველო'],
            ['zone_type', 'hunting', 'Hunting zone', 'სანადირო ზონა'],
            ['zone_type', 'fishing', 'Fishing zone', 'სათევზაო ზონა'],
        ];
        $now = now();
        foreach ($terms as $index => [$dimension, $code, $en, $ka]) {
            $id = DB::table('context_taxonomy_terms')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'dimension' => $dimension,
                'code' => $code,
                'default_label' => $en,
                'description' => null,
                'is_active' => true,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('context_taxonomy_term_translations')->insert([
                ['context_taxonomy_term_id' => $id, 'locale' => 'en', 'label' => $en, 'created_at' => $now, 'updated_at' => $now],
                ['context_taxonomy_term_id' => $id, 'locale' => 'ka', 'label' => $ka, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    private function seedProfiles(): void
    {
        $weights = [
            'activity_match' => 18,
            'species_exact_match' => 22,
            'species_category_match' => 10,
            'required_equipment_match' => 18,
            'method_match' => 8,
            'season_phase_match' => 6,
            'region_match' => 5,
            'zone_type_match' => 5,
            'general_relevance' => 0,
            'specification_completeness' => 4,
            'availability' => 4,
            'popularity' => 0,
            'recency' => 0,
        ];
        $configuration = [
            'minimum_score' => 35,
            'maximum_results' => 8,
            'candidate_limit' => 80,
            'merchandising_max_points' => 8,
            'activity_only_cap' => 60,
            'stock_behavior' => 'hide_out_of_stock',
            'backorder_behavior' => 'unsupported',
            'tie_break' => ['pinned', 'pin_priority', 'final_score', 'confidence', 'in_stock', 'merchandising_priority', 'published_at', 'slug'],
            'weights' => $weights,
        ];
        $placements = [
            'outdoor_context_result',
            'species_detail',
            'season_explorer',
            'map_location_result',
        ];
        $now = now();
        foreach ($placements as $placement) {
            $id = DB::table('recommendation_profiles')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'name' => 'Default '.$placement,
                'slug' => 'default-'.$placement,
                'placement' => $placement,
                'version' => 1,
                'status' => 'published',
                'minimum_score' => 35,
                'maximum_results' => 8,
                'candidate_limit' => 80,
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($weights as $dimension => $weight) {
                DB::table('recommendation_profile_weights')->insert([
                    'recommendation_profile_id' => $id,
                    'dimension' => $dimension,
                    'weight' => $weight,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
