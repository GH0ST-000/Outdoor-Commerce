<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 20: payment attempts, webhook inbox, idempotency, attempt history.
 *
 * One active attempt per order is enforced transactionally (MySQL cannot
 * express a partial unique index on status easily). Provider payment IDs
 * are unique per provider when present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('payment_method_code', 64);
            $table->string('status', 32);
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('provider_payment_id', 128)->nullable();
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('provider_status', 64)->nullable();
            $table->string('idempotency_key_hash', 64);
            $table->string('request_fingerprint', 64);
            $table->string('action_type', 32)->nullable();
            $table->text('redirect_url_encrypted')->nullable();
            $table->timestamp('provider_expires_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->string('failure_category', 32)->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_provider_sync_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index('provider_payment_id');
            $table->index('provider_transaction_id');
            $table->unique(['provider', 'provider_payment_id'], 'payment_attempts_provider_payment_unique');
        });

        Schema::create('payment_attempt_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason_code', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_attempt_id', 'created_at'], 'payment_attempt_history_attempt_created_index');
        });

        Schema::create('payment_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128)->nullable();
            $table->string('payload_hash', 64);
            $table->string('normalized_event_type', 64)->nullable();
            $table->string('provider_payment_id', 128)->nullable();
            $table->string('signature_status', 32);
            $table->string('processing_status', 32);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->json('safe_payload')->nullable();
            $table->json('safe_headers')->nullable();
            $table->timestamps();

            $table->index(['provider', 'processing_status']);
            $table->index('provider_payment_id');
            $table->index('payload_hash');
            $table->unique(['provider', 'provider_event_id'], 'payment_webhooks_provider_event_unique');
        });

        Schema::create('payment_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 128);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->string('endpoint', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key'], 'payment_idempotency_scope_key_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_idempotency_records');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_attempt_status_histories');
        Schema::dropIfExists('payment_attempts');
    }
};
