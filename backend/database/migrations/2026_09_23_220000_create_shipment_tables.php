<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 22: carrier-neutral shipments, allocation, events, webhook inbox, idempotency.
 *
 * Inventory is not deducted here — Day 20 already committed stock after verified payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('shipment_number', 32)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('fulfillment_type', 32);
            $table->string('provider_code', 32);
            $table->string('service_code', 64)->nullable();
            $table->string('status', 32);
            $table->string('carrier_display_name', 128)->nullable();
            $table->string('tracking_number', 64)->nullable();
            $table->string('public_tracking_url', 2048)->nullable();
            $table->string('provider_shipment_id', 128)->nullable();
            $table->string('pickup_location_public_id', 36)->nullable();
            $table->text('recipient_snapshot');
            $table->text('address_snapshot')->nullable();
            $table->json('pickup_location_snapshot')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->timestamp('estimated_delivery_from')->nullable();
            $table->timestamp('estimated_delivery_to')->nullable();
            $table->boolean('estimated_delivery_is_guaranteed')->default(false);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_provider_sync_at')->nullable();
            $table->string('exception_code', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider_code', 'status']);
            $table->index(['status', 'updated_at']);
            $table->unique(['provider_code', 'provider_shipment_id']);
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->unsignedInteger('packed_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('cancelled_quantity')->default(0);
            $table->timestamps();

            $table->unique(['shipment_id', 'order_item_id']);
            $table->index('order_item_id');
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('event_code', 64);
            $table->string('source', 32);
            $table->timestamp('occurred_at');
            $table->string('location_label', 128)->nullable();
            $table->string('customer_message_key', 64)->nullable();
            $table->string('internal_message', 1000)->nullable();
            $table->string('provider_event_id', 128)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['shipment_id', 'occurred_at', 'id']);
            $table->unique(['shipment_id', 'provider_event_id']);
        });

        Schema::create('shipment_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128)->nullable();
            $table->string('provider_shipment_id', 128)->nullable();
            $table->string('payload_hash', 64);
            $table->string('signature_status', 32);
            $table->string('processing_status', 32);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->json('safe_payload')->nullable();
            $table->json('safe_headers')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'processing_status']);
        });

        Schema::create('shipment_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 80);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->string('endpoint', 128);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_idempotency_records');
        Schema::dropIfExists('shipment_webhooks');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
