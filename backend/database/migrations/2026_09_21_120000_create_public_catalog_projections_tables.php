<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_catalog_variant_projections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_variant_id')->unique();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('currency_code', 3)->default('GEL');
            $table->unsignedInteger('base_price_minor')->nullable();
            $table->unsignedInteger('final_price_minor')->nullable();
            $table->unsignedInteger('discount_amount_minor')->nullable();
            $table->boolean('on_sale')->default(false);
            $table->unsignedInteger('available_to_sell')->default(0);
            $table->boolean('is_in_stock')->default(false);
            $table->boolean('is_low_stock')->default(false);
            $table->boolean('is_public')->default(false)->index();
            $table->string('pricing_signature', 64)->nullable();
            $table->unsignedInteger('pricing_version')->default(0);
            $table->unsignedInteger('inventory_version')->default(0);
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_public', 'currency_code'], 'pcvp_product_public_currency');
            $table->index(['is_public', 'final_price_minor', 'product_variant_id'], 'pcvp_public_price_sort');
            $table->index(['is_public', 'is_in_stock'], 'pcvp_public_stock');
            $table->index(['is_public', 'on_sale'], 'pcvp_public_sale');
        });

        Schema::create('public_catalog_product_projections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('currency_code', 3)->default('GEL');
            $table->unsignedInteger('minimum_base_price_minor')->nullable();
            $table->unsignedInteger('maximum_base_price_minor')->nullable();
            $table->unsignedInteger('minimum_final_price_minor')->nullable();
            $table->unsignedInteger('maximum_final_price_minor')->nullable();
            $table->unsignedInteger('public_variant_count')->default(0);
            $table->unsignedInteger('in_stock_variant_count')->default(0);
            $table->boolean('is_in_stock')->default(false);
            $table->boolean('is_on_sale')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('default_variant_id')->nullable();
            $table->unsignedInteger('pricing_version')->default(0);
            $table->unsignedInteger('inventory_version')->default(0);
            $table->timestamp('projected_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'currency_code'], 'pcpp_product_currency');
            $table->index(['is_public', 'currency_code'], 'pcpp_public_currency');
            $table->index(['is_public', 'minimum_final_price_minor', 'product_id'], 'pcpp_public_price_sort');
            $table->index(['is_public', 'is_in_stock'], 'pcpp_public_stock');
            $table->index(['is_public', 'is_on_sale'], 'pcpp_public_sale');
        });

        Schema::create('catalog_projection_refresh_states', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->timestamp('last_ran_at')->nullable();
            $table->timestamp('last_boundary_at')->nullable();
            $table->unsignedInteger('refreshed_variants')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_projection_refresh_states');
        Schema::dropIfExists('public_catalog_product_projections');
        Schema::dropIfExists('public_catalog_variant_projections');
    }
};
