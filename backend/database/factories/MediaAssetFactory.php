<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $createdAt = now();

        return [
            'uuid' => $uuid,
            'status' => MediaStatus::Pending,
            'original_disk' => MediaDisk::originals()->value,
            'original_path' => sprintf(
                'originals/%s/%s/%s/original.jpg',
                $createdAt->format('Y'),
                $createdAt->format('m'),
                $uuid,
            ),
            'original_filename' => 'rifle-scope',
            'original_extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 245760,
            'width' => 1600,
            'height' => 1200,
            'checksum_sha256' => hash('sha256', $uuid),
            'attempts' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Pending,
            'processed_at' => null,
            'processing_started_at' => null,
            'failure_code' => null,
            'failure_message' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Processing,
            'attempts' => 1,
            'processing_started_at' => now(),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Ready,
            'attempts' => 1,
            'processing_started_at' => now()->subSeconds(5),
            'processed_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);
    }

    public function failed(string $code = 'processing_error'): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Failed,
            'attempts' => 3,
            'failure_code' => $code,
            'failure_message' => 'Image processing failed. Please retry.',
        ]);
    }

    public function quarantined(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Quarantined,
            'attempts' => 1,
            'failure_code' => 'content_rejected',
            'failure_message' => 'The stored file failed the security re-check.',
        ]);
    }

    /**
     * Marks the asset as stalled so `media:recover-stuck` picks it up.
     */
    public function stuckProcessing(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Processing,
            'attempts' => 1,
            'processing_started_at' => now()->subHours(3),
        ]);
    }

    public function png(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/png',
            'original_extension' => 'png',
            'original_path' => str_replace('original.jpg', 'original.png', (string) $attributes['original_path']),
        ]);
    }

    /**
     * Attaches a ready WebP derivative for every preset, mirroring what the job
     * would have written.
     */
    public function withWebpDerivatives(): static
    {
        return $this->ready()->afterCreating(function (MediaAsset $asset): void {
            foreach (MediaPreset::ordered() as $preset) {
                // `forAsset` last: it derives the path from the resolved preset and
                // format, so it has to see them already applied.
                MediaDerivativeFactory::new()
                    ->preset($preset)
                    ->format(MediaFormat::Webp)
                    ->forAsset($asset)
                    ->create();
            }
        });
    }

    public function withWebpDerivative(MediaPreset $preset = MediaPreset::Card): static
    {
        return $this->ready()->afterCreating(function (MediaAsset $asset) use ($preset): void {
            MediaDerivativeFactory::new()
                ->preset($preset)
                ->format(MediaFormat::Webp)
                ->forAsset($asset)
                ->create();
        });
    }

    public function withJpegDerivative(MediaPreset $preset = MediaPreset::Card): static
    {
        return $this->ready()->afterCreating(function (MediaAsset $asset) use ($preset): void {
            MediaDerivativeFactory::new()
                ->preset($preset)
                ->format(MediaFormat::Jpeg)
                ->forAsset($asset)
                ->create();
        });
    }
}
