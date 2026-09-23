<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->json('name_translations');
            $table->json('address_translations');
            $table->string('phone', 32)->nullable();
            $table->json('working_hours_translations')->nullable();
            $table->json('instructions_translations')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('fulfillment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type', 32);
            $table->string('code', 64)->unique();
            $table->json('name_translations');
            $table->json('description_translations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->unsignedBigInteger('free_above_minor')->nullable();
            $table->unsignedSmallInteger('estimated_min_days')->nullable();
            $table->unsignedSmallInteger('estimated_max_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('requires_address')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('type');
        });

        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->json('name_translations');
            $table->char('country_code', 2);
            $table->string('region', 128)->nullable();
            $table->string('municipality_or_city', 128)->nullable();
            $table->string('postal_code_pattern', 64)->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('pickup_locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['country_code', 'is_active']);
            $table->index('pickup_location_id');
        });

        Schema::create('delivery_rate_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfillment_method_id')->constrained('fulfillment_methods')->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->constrained('delivery_zones')->restrictOnDelete();
            $table->unsignedBigInteger('minimum_subtotal_minor')->nullable();
            $table->unsignedBigInteger('maximum_subtotal_minor')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('free_above_minor')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['fulfillment_method_id', 'is_active', 'priority'], 'delivery_rate_method_active_priority_index');
            $table->index('delivery_zone_id');
        });

        Schema::create('checkout_addresses', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->string('recipient_first_name', 80);
            $table->string('recipient_last_name', 80);
            $table->string('phone', 32);
            $table->char('country_code', 2);
            $table->string('region', 128)->nullable();
            $table->string('municipality_or_city', 128)->nullable();
            $table->string('district', 128)->nullable();
            $table->string('street', 160)->nullable();
            $table->string('house_number', 32)->nullable();
            $table->string('apartment', 32)->nullable();
            $table->string('entrance', 32)->nullable();
            $table->string('floor', 16)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('landmark', 160)->nullable();
            $table->string('delivery_instructions', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cart_id')->constrained('carts')->restrictOnDelete();
            $table->string('guest_token_hash', 64)->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('version')->default(0);
            $table->char('currency', 3);
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('customer_note', 1000)->nullable();
            $table->foreignId('fulfillment_method_id')->nullable()->constrained('fulfillment_methods')->nullOnDelete();
            $table->foreignId('pickup_location_id')->nullable()->constrained('pickup_locations')->nullOnDelete();
            $table->foreignId('shipping_address_id')->nullable()->constrained('checkout_addresses')->nullOnDelete();
            $table->boolean('billing_same_as_shipping')->default(true);
            $table->foreignId('billing_address_id')->nullable()->constrained('checkout_addresses')->nullOnDelete();
            $table->unsignedBigInteger('current_quote_id')->nullable();
            $table->unsignedInteger('quote_refresh_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['guest_token_hash', 'status']);
            $table->index(['cart_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('checkout_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status', 32);
            $table->unsignedInteger('cart_version');
            $table->char('currency', 3);
            $table->unsignedBigInteger('items_subtotal_minor');
            $table->unsignedBigInteger('discount_total_minor')->default(0);
            $table->unsignedBigInteger('delivery_total_minor')->default(0);
            $table->unsignedBigInteger('tax_total_minor')->default(0);
            $table->unsignedBigInteger('grand_total_minor');
            $table->boolean('price_includes_tax')->default(true);
            $table->string('quote_fingerprint', 64);
            $table->json('fulfillment_snapshot')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique(['checkout_session_id', 'revision']);
            $table->index(['status', 'expires_at']);
            $table->index('quote_fingerprint');
        });

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->foreign('current_quote_id')->references('id')->on('checkout_quotes')->nullOnDelete();
        });

        Schema::create('checkout_quote_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_quote_id')->constrained('checkout_quotes')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 64);
            $table->string('product_name', 255);
            $table->string('variant_label', 255);
            $table->string('slug', 255)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_base_price_minor');
            $table->unsignedBigInteger('unit_effective_price_minor');
            $table->unsignedBigInteger('unit_discount_minor')->default(0);
            $table->unsignedBigInteger('line_subtotal_minor');
            $table->unsignedBigInteger('line_discount_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->json('promotion_snapshot')->nullable();
            $table->json('attribute_snapshot')->nullable();
            $table->json('media_snapshot')->nullable();
            $table->json('restriction_snapshot')->nullable();
            $table->string('reservation_key', 64)->nullable();
            $table->timestamp('created_at');

            $table->index('checkout_quote_id');
            $table->index('variant_id');
        });

        Schema::create('checkout_quote_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_quote_id')->constrained('checkout_quotes')->cascadeOnDelete();
            $table->foreignId('quote_line_id')->nullable()->constrained('checkout_quote_lines')->nullOnDelete();
            $table->string('type', 32);
            $table->string('code', 64);
            $table->string('label', 160);
            $table->bigInteger('amount_minor');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('checkout_quote_id');
        });

        Schema::create('checkout_restriction_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('outcome', 32);
            $table->string('code', 64);
            $table->json('message_translations');
            $table->string('required_action', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::create('checkout_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 128);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->string('endpoint', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotency_records');
        Schema::dropIfExists('checkout_restriction_rules');
        Schema::dropIfExists('checkout_quote_adjustments');
        Schema::dropIfExists('checkout_quote_lines');
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropForeign(['current_quote_id']);
        });
        Schema::dropIfExists('checkout_quotes');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('checkout_addresses');
        Schema::dropIfExists('delivery_rate_rules');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('fulfillment_methods');
        Schema::dropIfExists('pickup_locations');
    }
};
