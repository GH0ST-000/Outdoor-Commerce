<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\MediaAttachmentRole;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAttachment>
 */
class MediaAttachmentFactory extends Factory
{
    protected $model = MediaAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'mediable_type' => (new Product)->getMorphClass(),
            'mediable_id' => Product::factory(),
            'role' => MediaAttachmentRole::Gallery,
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'mediable_type' => $product->getMorphClass(),
            'mediable_id' => $product->id,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn () => [
            'mediable_type' => $variant->getMorphClass(),
            'mediable_id' => $variant->id,
        ]);
    }

    public function forAsset(MediaAsset $asset): static
    {
        return $this->state(fn () => ['media_asset_id' => $asset->id]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['media_asset_id' => MediaAsset::factory()->pending()]);
    }

    public function ready(): static
    {
        return $this->state(fn () => ['media_asset_id' => MediaAsset::factory()->withWebpDerivatives()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['media_asset_id' => MediaAsset::factory()->failed()]);
    }

    public function quarantined(): static
    {
        return $this->state(fn () => ['media_asset_id' => MediaAsset::factory()->quarantined()]);
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function sortOrder(int $sortOrder): static
    {
        return $this->state(fn () => ['sort_order' => $sortOrder]);
    }

    public function withFocalPoint(float $x = 0.5, float $y = 0.5): static
    {
        return $this->state(fn () => [
            'focal_point_x' => (string) $x,
            'focal_point_y' => (string) $y,
        ]);
    }

    public function withKaAlt(string $altText = 'ოპტიკური სამიზნე'): static
    {
        return $this->afterCreating(function (MediaAttachment $attachment) use ($altText): void {
            $attachment->translations()->create([
                'locale' => 'ka',
                'alt_text' => $altText,
            ]);
        });
    }

    public function withKaEnAlt(string $ka = 'ოპტიკური სამიზნე', string $en = 'Rifle scope'): static
    {
        return $this->withKaAlt($ka)->afterCreating(function (MediaAttachment $attachment) use ($en): void {
            $attachment->translations()->create([
                'locale' => 'en',
                'alt_text' => $en,
            ]);
        });
    }
}
