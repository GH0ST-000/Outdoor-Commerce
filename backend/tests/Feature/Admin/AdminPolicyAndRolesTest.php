<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\UserPolicy;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('denies user administration by default and allows with permissions', function (): void {
    $policy = new UserPolicy;
    $customer = User::factory()->create();
    $admin = $this->createAdmin();
    $target = User::factory()->create();

    expect($policy->viewAny($customer))->toBeFalse();
    expect($policy->manageStatus($customer, $target))->toBeFalse();
    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->manageRoles($admin, $target))->toBeTrue();
});

it('returns the roles matrix for admins', function (): void {
    $admin = $this->createAdmin();

    $response = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing([
        Role::Admin->value,
        Role::CatalogManager->value,
        Role::OrderManager->value,
        Role::LegalEditor->value,
    ]);
});
