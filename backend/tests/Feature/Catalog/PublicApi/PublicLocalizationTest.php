<?php

declare(strict_types=1);

use Tests\Support\PublicCatalogFixtures;

it('returns georgian content for ka and english for en with georgian fallback', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ოპტიკური სამიზნე',
        'en_name' => 'Rifle scope',
        'ka_slug' => 'optikuri-samizne',
        'en_slug' => 'rifle-scope',
    ]);

    $ka = $this->getJson('/api/v1/catalog/products/optikuri-samizne?locale=ka');
    $ka->assertOk()
        ->assertHeader('Content-Language', 'ka')
        ->assertJsonPath('data.name', 'ოპტიკური სამიზნე')
        ->assertJsonPath('data.slug', 'optikuri-samizne')
        ->assertJsonPath('data.used_fallback', false);
    expect($ka->headers->get('Vary'))->toContain('Accept-Language');

    $en = $this->getJson('/api/v1/catalog/products/rifle-scope?locale=en');
    $en->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.name', 'Rifle scope')
        ->assertJsonPath('data.used_fallback', false);

    $kaOnly = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'მხოლოდ ქართული',
        'ka_slug' => 'mxolod-kartuli',
        'en_slug' => null,
    ]);

    $fallback = $this->getJson('/api/v1/catalog/products/mxolod-kartuli?locale=en');
    $fallback->assertOk()
        ->assertJsonPath('data.name', 'მხოლოდ ქართული')
        ->assertJsonPath('data.used_fallback', true)
        ->assertJsonPath('meta.used_fallback', true);
});

it('resolves english storefront slugs while the active locale is georgian', function (): void {
    $category = PublicCatalogFixtures::category([
        'ka_slug' => 'nadiroba',
        'ka_name' => 'ნადირობა',
        'en_slug' => 'hunting',
        'en_name' => 'Hunting',
    ]);
    $fixture = PublicCatalogFixtures::publicProduct([
        'category' => $category,
        'ka_name' => 'დემო ოპტიკა',
        'ka_slug' => 'demo-optika',
        'en_name' => 'Demo hunting optics',
        'en_slug' => 'demo-hunting-optics',
    ]);

    $this->getJson('/api/v1/catalog/categories/hunting?locale=ka')
        ->assertOk()
        ->assertJsonPath('data.name', 'ნადირობა');

    $this->getJson('/api/v1/catalog/products?locale=ka&category=hunting')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.name', 'დემო ოპტიკა');

    $this->getJson('/api/v1/catalog/products/demo-hunting-optics?locale=ka')
        ->assertOk()
        ->assertJsonPath('data.name', 'დემო ოპტიკა')
        ->assertJsonPath('data.id', $fixture['product']->id);
});

it('resolves georgian unicode slugs and rejects unsupported locales', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_slug' => 'ნადირობის-ოპტიკა',
        'ka_name' => 'ნადირობის ოპტიკა',
        'en_slug' => 'hunting-optics',
    ]);

    $this->getJson('/api/v1/catalog/products/'.rawurlencode('ნადირობის-ოპტიკა').'?locale=ka')
        ->assertOk()
        ->assertJsonPath('data.slug', 'ნადირობის-ოპტიკა')
        ->assertJsonPath('data.name', 'ნადირობის ოპტიკა');

    $this->getJson('/api/v1/catalog/products/hunting-optics?locale=fr')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_LOCALE_UNSUPPORTED');

    $this->withHeaders(['X-Locale' => 'en'])
        ->getJson('/api/v1/catalog/products/hunting-optics')
        ->assertOk()
        ->assertHeader('Content-Language', 'en');
});

it('localizes breadcrumbs media alt and alternate locale paths', function (): void {
    $parent = PublicCatalogFixtures::category([
        'ka_slug' => 'nadiroba',
        'ka_name' => 'ნადირობა',
        'en_slug' => 'hunting',
        'en_name' => 'Hunting',
    ]);
    $child = PublicCatalogFixtures::category([
        'parent' => $parent,
        'ka_slug' => 'optika',
        'ka_name' => 'ოპტიკა',
        'en_slug' => 'optics',
        'en_name' => 'Optics',
    ]);
    $fixture = PublicCatalogFixtures::publicProduct([
        'category' => $child,
        'ka_slug' => 'samizne',
        'en_slug' => 'scope',
    ]);

    $ka = $this->getJson('/api/v1/catalog/products/samizne?locale=ka')->assertOk();
    $crumbs = $ka->json('data.breadcrumbs');
    expect($crumbs[0]['name'])->toBe('ნადირობა');
    expect($crumbs[1]['name'])->toBe('ოპტიკა');
    expect($ka->json('data.gallery.0.alt'))->toBe('ოპტიკური სამიზნე');
    expect($ka->json('data.alternate_locale_paths.en'))->toBe('/products/scope');

    $en = $this->getJson('/api/v1/catalog/products/scope?locale=en')->assertOk();
    expect($en->json('data.breadcrumbs.0.name'))->toBe('Hunting');
    expect($en->json('data.gallery.0.alt'))->toBe('Rifle scope');
});
