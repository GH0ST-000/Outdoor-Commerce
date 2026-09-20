<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 10: warehouses, balance projection, immutable ledger, reservations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('status', 16)->default('active');
            $table->boolean('is_default')->default(false);
            $table->string('country_code', 2)->default('GE');
            $table->string('city', 120)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index(['status', 'is_default']);
            $table->index('is_default');
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('safety_stock')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_variant_id'], 'inventory_balances_warehouse_variant_unique');
            $table->index(['warehouse_id', 'on_hand']);
            $table->index(['product_variant_id']);
            $table->index(['reorder_point']);
        });

        Schema::create('inventory_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('type', 40);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64)->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 128)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique('uuid');
            $table->unique('idempotency_key');
            $table->index(['type', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('performed_by');
        });

        Schema::create('inventory_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_operation_id')->constrained('inventory_operations')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('movement_type', 40);
            $table->integer('quantity_delta');
            $table->unsignedInteger('on_hand_after');
            $table->unsignedInteger('reserved_after');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_id', 'product_variant_id', 'created_at'], 'inventory_ledger_balance_history_index');
            $table->index('inventory_operation_id');
            $table->index(['movement_type', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reservation_key');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 16)->default('active');
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 128)->nullable();
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('reservation_key');
            $table->unique('idempotency_key');
            $table->index(['status', 'expires_at']);
            $table->index(['warehouse_id', 'product_variant_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_ledger_entries');
        Schema::dropIfExists('inventory_operations');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('warehouses');
    }
};
