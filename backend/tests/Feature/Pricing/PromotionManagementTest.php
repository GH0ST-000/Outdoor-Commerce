<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use Carbon\CarbonImmutable;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\PricingFixtures;

uses(InteractsWithAccessControl::class);

it('creates activates and pauses a promotion', function (): void {
    $manager = $this->createUserWithRole(Role::PricingManager);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->active()->for($product)->create();
    PricingFixtures::publishedPrice($variant, 12_000);

    $create = $this->actingAs($manager, 'web')->postJson('/api/v1/admin/promotions', [
        'code' => 'spring-15',
        'name' => 'Spring 15%',
        'discount_type' => 'percentage',
        'percentage_basis_points' => 1500,
        'currency_code' => 'GEL',
        'priority' => 20,
        'stacking_mode' => 'exclusive',
        'starts_at' => CarbonImmutable::now('UTC')->subHour()->toIso8601String(),
    ]);
    $create->assertCreated();
    $id = $create->json('data.id');

    $this->actingAs($manager, 'web')
        ->putJson("/api/v1/admin/promotions/{$id}/targets", [
            'targets' => [
                ['target_type' => PromotionTargetType::AllProducts->value, 'target_id' => null, 'mode' => PromotionTargetMode::Include->value],
            ],
        ])
        ->assertOk();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/promotions/{$id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', PromotionStatus::Active->value);

    expect(AuditLog::query()->where('event', AuditEvent::PromotionActivated->value)->exists())->toBeTrue();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/promotions/{$id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', PromotionStatus::Paused->value);
});

it('denies catalog manager from activating promotions', function (): void {
    $catalog = $this->createUserWithRole(Role::CatalogManager);
    $promo = Promotion::factory()->create();

    $this->actingAs($catalog, 'web')
        ->postJson("/api/v1/admin/promotions/{$promo->id}/activate")
        ->assertForbidden();
});

it('previews a promotion without writing', function (): void {
    $manager = $this->createUserWithRole(Role::PricingManager);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->active()->for($product)->create();
    PricingFixtures::publishedPrice($variant, 10_000);

    $before = Promotion::query()->count();

    $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/promotions/preview', [
            'variant_ids' => [$variant->id],
            'promotion' => [
                'discount_type' => 'percentage',
                'percentage_basis_points' => 1000,
                'priority' => 5,
                'stacking_mode' => 'exclusive',
                'targets' => [
                    ['target_type' => 'all_products', 'target_id' => null, 'mode' => 'include'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.samples.0.eligible', true)
        ->assertJsonPath('data.samples.0.final_amount_minor', 9000);

    expect(Promotion::query()->count())->toBe($before);
});
