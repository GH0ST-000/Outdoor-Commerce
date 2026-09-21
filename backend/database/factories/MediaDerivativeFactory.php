<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\MediaDisk;
use App\Domains\Catalog\Enums\MediaFormat;
use App\Domains\Catalog\Enums\MediaPreset;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaDerivative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaDerivative>
 */
class MediaDerivativeFactory extends Factory
{
    protected $model = MediaDerivative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory()->ready(),
            'preset' => MediaPreset::Card,
            'format' => MediaFormat::Webp,
            'disk' => MediaDisk::derivatives()->value,
            'path' => 'derivatives/2026/09/'.$this->faker->uuid().'/card.webp',
            'width' => MediaPreset::Card->maxWidth(),
            'height' => MediaPreset::Card->maxHeight(),
            'byte_size' => 18432,
        ];
    }

    public function forAsset(MediaAsset $asset): static
    {
        return $this->state(fn (array $attributes) => [
            'media_asset_id' => $asset->id,
            'path' => sprintf(
                'derivatives/%s/%s/%s/%s.%s',
                ($asset->created_at ?? now())->format('Y'),
                ($asset->created_at ?? now())->format('m'),
                $asset->uuid,
                $attributes['preset'] instanceof MediaPreset
                    ? $attributes['preset']->value
                    : (string) $attributes['preset'],
                $attributes['format'] instanceof MediaFormat
                    ? $attributes['format']->extension()
                    : (string) $attributes['format'],
            ),
        ]);
    }

    public function preset(MediaPreset $preset): static
    {
        return $this->state(fn () => [
            'preset' => $preset,
            'width' => $preset->maxWidth(),
            'height' => $preset->maxHeight(),
        ]);
    }

    public function format(MediaFormat $format): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => $format,
            'path' => preg_replace(
                '/\.[a-z0-9]+$/i',
                '.'.$format->extension(),
                (string) $attributes['path'],
            ),
        ]);
    }
}
