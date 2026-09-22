<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token_hash', 64)->nullable();
            $table->string('status', 32);
            $table->char('currency', 3);
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('unique_item_count')->default(0);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_total_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('merged_into_cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->unsignedBigInteger('converted_order_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique('guest_token_hash');
            $table->index('status');
            $table->index('expires_at');
            $table->index('last_activity_at');
            $table->index('merged_into_cart_id');
            $table->index(['user_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_at_add_minor');
            $table->unsignedBigInteger('discount_at_add_minor')->default(0);
            $table->char('currency', 3);
            $table->string('configuration_hash', 64)->nullable();
            $table->string('product_name_at_add', 255)->nullable();
            $table->string('variant_name_at_add', 255)->nullable();
            $table->string('sku_at_add', 64)->nullable();
            $table->timestamp('added_at');
            $table->timestamps();

            $table->unique(['cart_id', 'variant_id']);
            $table->index('product_id');
            $table->index('variant_id');
        });

        Schema::create('cart_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 128);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->string('endpoint', 64);
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
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
        Schema::dropIfExists('cart_idempotency_records');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
