<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->char('currency_code', 3);
            $table->string('status', 32);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('prices_include_tax');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index(['status', 'currency_code']);
            $table->index(['currency_code', 'is_default', 'status']);
            $table->index('priority');
        });

        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_variant_id']);
            $table->index('product_variant_id');
        });

        Schema::create('price_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_price_id')->constrained('variant_prices')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['variant_price_id', 'status', 'starts_at', 'ends_at'], 'price_periods_effective_idx');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->string('discount_type', 32);
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('stacking_mode', 32);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('maximum_discount_minor')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['discount_type', 'stacking_mode']);
            $table->index('priority');
        });

        Schema::create('promotion_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('mode', 16);
            $table->timestamps();

            $table->unique(
                ['promotion_id', 'target_type', 'target_id', 'mode'],
                'promotion_targets_unique'
            );
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('price_periods');
        Schema::dropIfExists('variant_prices');
        Schema::dropIfExists('price_lists');
    }
};
