<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('checkout_session_id');
            $table->unsignedBigInteger('checkout_quote_id');
            $table->unsignedBigInteger('cart_id');
            $table->string('status', 32);
            $table->string('payment_status', 32);
            $table->string('fulfillment_status', 32);
            $table->string('currency', 3);
            $table->unsignedInteger('items_subtotal_minor');
            $table->unsignedInteger('discount_total_minor');
            $table->unsignedInteger('delivery_total_minor');
            $table->unsignedInteger('tax_total_minor');
            $table->unsignedInteger('grand_total_minor');
            $table->boolean('price_includes_tax')->default(true);
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->text('customer_note')->nullable();
            $table->string('fulfillment_method_code', 64);
            $table->string('fulfillment_method_name');
            $table->unsignedInteger('quote_revision');
            $table->string('quote_fingerprint', 64);
            $table->text('contact_snapshot');
            $table->text('shipping_address_snapshot')->nullable();
            $table->text('billing_address_snapshot')->nullable();
            $table->json('fulfillment_snapshot');
            $table->string('access_token_hash', 64)->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('checkout_quote_id');
            $table->unique('checkout_session_id');
            $table->index('user_id');
            $table->index('cart_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index('reservation_expires_at');
            $table->index('access_token_hash');

            $table->foreign('checkout_session_id')->references('id')->on('checkout_sessions');
            $table->foreign('checkout_quote_id')->references('id')->on('checkout_quotes');
            $table->foreign('cart_id')->references('id')->on('carts');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('sku');
            $table->string('product_name');
            $table->string('variant_name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_base_price_minor');
            $table->unsignedInteger('unit_effective_price_minor');
            $table->unsignedInteger('unit_discount_minor');
            $table->unsignedInteger('line_subtotal_minor');
            $table->unsignedInteger('line_discount_minor');
            $table->unsignedInteger('line_total_minor');
            $table->string('currency', 3);
            $table->json('product_snapshot');
            $table->json('variant_snapshot');
            $table->json('attribute_snapshot')->nullable();
            $table->json('promotion_snapshot')->nullable();
            $table->json('media_snapshot')->nullable();
            $table->json('restriction_snapshot')->nullable();
            $table->string('reservation_key')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('type', 32);
            $table->string('code', 64);
            $table->string('label');
            $table->integer('amount_minor');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('order_id');
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason_code', 64);
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope');
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
        Schema::dropIfExists('order_idempotency_records');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
