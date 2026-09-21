<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\MediaAttachmentTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAttachmentTranslation>
 */
class MediaAttachmentTranslationFactory extends Factory
{
    protected $model = MediaAttachmentTranslation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_attachment_id' => MediaAttachment::factory(),
            'locale' => 'ka',
            'alt_text' => 'ოპტიკური სამიზნე',
            'caption' => null,
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(fn () => ['locale' => $locale]);
    }

    public function withoutAltText(): static
    {
        return $this->state(fn () => ['alt_text' => null]);
    }
}
