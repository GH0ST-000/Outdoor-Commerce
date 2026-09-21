<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            // Storage-path identifier only. The primary key stays an integer.
            $table->uuid('uuid');
            $table->string('status', 16)->default('pending');
            $table->string('original_disk', 32);
            $table->string('original_path', 512);
            $table->string('original_filename', 255)->nullable();
            $table->string('original_extension', 16);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid');
            $table->index(['status', 'created_at']);
            $table->index(['status', 'processing_started_at']);
            $table->index('checksum_sha256');
        });

        Schema::create('media_derivatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('preset', 32);
            $table->string('format', 16);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('byte_size');
            $table->timestamps();

            $table->unique(['media_asset_id', 'preset', 'format'], 'media_derivatives_asset_preset_format_unique');
            $table->index(['media_asset_id', 'format']);
        });

        Schema::create('media_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            // Morph aliases only: `product` / `product_variant` (enforceMorphMap).
            $table->string('mediable_type', 64);
            $table->unsignedBigInteger('mediable_id');
            $table->string('role', 32)->default('gallery');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->decimal('focal_point_x', 6, 5)->nullable();
            $table->decimal('focal_point_y', 6, 5)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // deleted_at participates so a removed attachment can be re-added.
            $table->unique(
                ['mediable_type', 'mediable_id', 'media_asset_id', 'role', 'deleted_at'],
                'media_attachments_owner_asset_unique',
            );
            $table->index(['mediable_type', 'mediable_id', 'role', 'sort_order'], 'media_attachments_owner_order_index');
            $table->index(['mediable_type', 'mediable_id', 'is_primary'], 'media_attachments_owner_primary_index');
            $table->index('media_asset_id');
        });

        Schema::create('media_attachment_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_attachment_id')->constrained('media_attachments')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('alt_text', 300)->nullable();
            $table->string('caption', 600)->nullable();
            $table->timestamps();

            $table->unique(['media_attachment_id', 'locale'], 'media_attachment_translations_unique');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachment_translations');
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('media_derivatives');
        Schema::dropIfExists('media_assets');
    }
};
