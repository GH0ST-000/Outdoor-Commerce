<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'attr_'.$this->faker->unique()->numerify('######'),
            'type' => AttributeType::Select,
            'status' => AttributeStatus::Draft,
            'is_filterable' => false,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Attribute $attribute): void {
            if ($attribute->translations()->exists()) {
                return;
            }

            AttributeTranslation::query()->create([
                'attribute_id' => $attribute->id,
                'locale' => 'ka',
                'name' => 'ატრიბუტი-'.$attribute->id,
            ]);
        });
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => AttributeStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AttributeStatus::Archived]);
    }

    public function color(): static
    {
        return $this->state(fn () => ['type' => AttributeType::Color]);
    }

    public function filterable(): static
    {
        return $this->state(fn () => ['is_filterable' => true]);
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
        return $this->afterCreating(function (Attribute $attribute): void {
            $attribute->translations()->where('locale', 'ka')->delete();
            $attribute->unsetRelation('translations');
        });
    }
}
