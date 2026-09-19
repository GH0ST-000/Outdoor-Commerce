<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\AttributeValueTranslation;
use App\Domains\Catalog\Support\ColorHex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory()->active(),
            'code' => 'val_'.$this->faker->unique()->numerify('######'),
            'status' => AttributeValueStatus::Draft,
            'sort_order' => 0,
            'color_hex' => null,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AttributeValue $value): void {
            if ($value->translations()->exists()) {
                return;
            }

            AttributeValueTranslation::query()->create([
                'attribute_value_id' => $value->id,
                'locale' => 'ka',
                'name' => 'მნიშვნელობა-'.$value->id,
            ]);
        });
    }

    public function forAttribute(Attribute $attribute): static
    {
        return $this->state(fn () => ['attribute_id' => $attribute->id]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => AttributeValueStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AttributeValueStatus::Archived]);
    }

    public function withColor(string $hex = '#1a2b3c'): static
    {
        return $this->state(fn () => ['color_hex' => ColorHex::normalize($hex)]);
    }

    public function code(string $code): static
    {
        return $this->state(fn () => ['code' => $code]);
    }

    /**
     * Skips the automatic Georgian translation so activation guards can be tested.
     */
    public function withoutGeorgian(): static
    {
        return $this->afterCreating(function (AttributeValue $value): void {
            $value->translations()->where('locale', 'ka')->delete();
            $value->unsetRelation('translations');
        });
    }
}
