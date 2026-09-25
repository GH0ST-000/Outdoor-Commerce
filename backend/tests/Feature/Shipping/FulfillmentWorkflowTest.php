<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentEvent;
use Tests\Support\FulfillmentFixtures;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    config()->set('shipping.allowed_tracking_hosts', ['tracking.example.com']);
    config()->set('shipping.https_required', false);
});

function asGuest(): void
{
    auth()->forgetGuards();
    test()->flushHeaders();
}

it('creates a pickup shipment for a paid order and hides it from another customer', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 2, 'p1');
    $manager = $this->createUserWithRole(Role::OrderManager);

    $created = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('shp-1'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
            'internal_note' => 'Pick from aisle 2',
        ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.provider.manual', true)
        ->assertJsonMissingPath('data.warehouse_id');

    expect($created->json('data.internal_timeline.0.internal_message'))->toBe('Pick from aisle 2')
        ->and(Order::query()->where('public_id', $paid['order_id'])->first()?->fulfillment_status)
        ->toBe(FulfillmentStatus::Processing);

    asGuest();
    $customer = test()->withUnencryptedCookie((string) config('order.cookie.name'), $paid['order_cookie'])
        ->getJson('/api/v1/orders/'.$paid['order_id'].'/fulfillment')
        ->assertOk()
        ->assertJsonPath('data.fulfillment_status', 'processing')
        ->assertJsonPath('data.shipments.0.items.0.quantity', 1)
        ->assertJsonMissingPath('data.shipments.0.internal_timeline')
        ->assertJsonMissingPath('data.shipments.0.warehouse_code');

    expect($customer->json('data.shipments.0.timeline.0.message'))->not->toContain('aisle')
        ->and($customer->json('data.remaining_items.0.quantity'))->toBe(1);

    asGuest();
    $this->defaultCookies = [];
    $this->unencryptedCookies = [];
    test()->getJson('/api/v1/orders/'.$paid['order_id'].'/fulfillment')->assertNotFound();
    test()->getJson('/api/v1/orders/'.$paid['order_id'].'?tracking='.$created->json('data.tracking.number'))
        ->assertNotFound();

    expect(AuditLog::query()->where('event', AuditEvent::ShipmentCreated->value)->exists())->toBeTrue();
});

it('rejects unpaid, unauthorized, over-allocated and unsafe tracking URLs', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 2, 'ov');
    $this->defaultCookies = [];
    $this->unencryptedCookies = [];
    $unpaid = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'unp');
    $manager = $this->createUserWithRole(Role::OrderManager);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('unpaid'))
        ->postJson('/api/v1/admin/orders/'.$unpaid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'quantity' => 1]],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'FULFILLMENT_ORDER_NOT_PAID');

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('jsurl'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
            'tracking_url' => 'javascript:alert(1)',
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'SHIPMENT_TRACKING_URL_INVALID');

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('full'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 2]],
        ])->assertCreated();

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('over'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'FULFILLMENT_QUANTITY_EXCEEDED');
});

it('picks, packs, marks pickup ready and collected, then fulfills the order once', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 1, 'pk');
    $manager = $this->createUserWithRole(Role::OrderManager);
    $ledgerBefore = InventoryLedgerEntry::query()->count();

    $created = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-c'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertCreated();

    $id = (string) $created->json('data.id');
    $version = (int) $created->json('data.version');

    $prep = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-s'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/start-preparation', [
            'expected_version' => $version,
        ])->assertOk()
        ->assertJsonPath('data.status', 'preparing');

    $picked = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-p'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/pick', [
            'expected_version' => $prep->json('data.version'),
        ])->assertOk();

    $packed = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-k'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/pack', [
            'expected_version' => $picked->json('data.version'),
        ])->assertOk();

    $ready = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-r'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-ready-for-pickup', [
            'expected_version' => $packed->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'ready_for_pickup');

    asGuest();
    test()->withUnencryptedCookie((string) config('order.cookie.name'), $paid['order_cookie'])
        ->postJson('/api/v1/orders/'.$paid['order_id'].'/cancel')
        ->assertStatus(422);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-g'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-collected', [
            'expected_version' => $ready->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'collected');

    $order = Order::query()->where('public_id', $paid['order_id'])->first();
    expect($order?->fulfillment_status)->toBe(FulfillmentStatus::Fulfilled)
        ->and($order?->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order?->status)->toBe(OrderStatus::Confirmed)
        ->and(InventoryLedgerEntry::query()->count())->toBe($ledgerBefore)
        ->and(InventoryReservation::query()->where('reference_id', $paid['order_id'])->where('status', InventoryReservationStatus::Committed)->count())->toBe(1);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('pk-again'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-ready-for-pickup', [
            'expected_version' => $ready->json('data.version') + 1,
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'SHIPMENT_ALREADY_COLLECTED');

    expect(ShipmentEvent::query()->where('shipment_id', Shipment::query()->where('public_id', $id)->value('id'))->count())
        ->toBeGreaterThanOrEqual(5);
});

it('supports partial shipments, delivery dispatch, and cancelled draft allocation', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 2, 'dl');
    $manager = $this->createUserWithRole(Role::OrderManager);

    $first = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('dl-a'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertCreated();

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('dl-b'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertCreated();

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('dl-x'))
        ->postJson('/api/v1/admin/shipments/'.$first->json('data.id').'/cancel', [
            'expected_version' => $first->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $third = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('dl-c'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
            'tracking_number' => 'TRACK-1',
            'tracking_url' => 'https://tracking.example.com/TRACK-1',
            'carrier_display_name' => 'Manual courier',
        ])->assertCreated();

    asGuest();
    test()->withUnencryptedCookie((string) config('order.cookie.name'), $paid['order_cookie'])
        ->getJson('/api/v1/orders/'.$paid['order_id'].'/fulfillment')
        ->assertOk()
        ->assertJsonPath('data.remaining_items', []);

    $this->artisan('shipments:reconcile')->assertSuccessful();
    $this->artisan('shipments:detect-stale')->assertSuccessful();

    test()->postJson('/api/v1/shipments/webhooks/manual', ['status' => 'delivered'])
        ->assertStatus(422);
    test()->postJson('/api/v1/shipments/webhooks/not_a_carrier', [])
        ->assertStatus(404);

    expect($third->json('data.tracking.number'))->toBe('TRACK-1');
});

it('dispatches a delivery shipment through pick, pack, and in-transit to delivered', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidDeliveryOrder($fixture['variant']->id, 1, 'del');
    $manager = $this->createUserWithRole(Role::OrderManager);

    $created = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-c'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'fulfillment_type' => 'delivery',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertCreated()
        ->assertJsonPath('data.type', 'delivery');

    $id = (string) $created->json('data.id');
    $version = (int) $created->json('data.version');

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-pickup'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-ready-for-pickup', [
            'expected_version' => $version,
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'SHIPMENT_INVALID_TRANSITION');

    $prep = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-s'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/start-preparation', [
            'expected_version' => $version,
        ])->assertOk();

    $picked = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-p'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/pick', [
            'expected_version' => $prep->json('data.version'),
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertOk();

    $packed = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-k'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/pack', [
            'expected_version' => $picked->json('data.version'),
        ])->assertOk();

    $ready = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-r'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/ready-for-dispatch', [
            'expected_version' => $packed->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'ready_for_dispatch');

    $shipped = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-d'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/dispatch', [
            'expected_version' => $ready->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'shipped');

    asGuest();
    test()->withUnencryptedCookie((string) config('order.cookie.name'), $paid['order_cookie'])
        ->postJson('/api/v1/orders/'.$paid['order_id'].'/cancel')
        ->assertStatus(422);

    $transit = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-t'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-in-transit', [
            'expected_version' => $shipped->json('data.version'),
        ])->assertOk();

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-x'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/cancel', [
            'expected_version' => $transit->json('data.version'),
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'SHIPMENT_ALREADY_DISPATCHED');

    $out = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-o'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-out-for-delivery', [
            'expected_version' => $transit->json('data.version'),
        ])->assertOk();

    $failed = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-f'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/record-delivery-attempt-failed', [
            'expected_version' => $out->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'delivery_attempt_failed');

    $retry = $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-o2'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-out-for-delivery', [
            'expected_version' => $failed->json('data.version'),
        ])->assertOk();

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-done'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-delivered', [
            'expected_version' => $retry->json('data.version'),
        ])->assertOk()
        ->assertJsonPath('data.status', 'delivered');

    expect(Order::query()->where('public_id', $paid['order_id'])->first()?->fulfillment_status)
        ->toBe(FulfillmentStatus::Fulfilled);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('del-again'))
        ->postJson('/api/v1/admin/shipments/'.$id.'/mark-in-transit', [
            'expected_version' => $retry->json('data.version') + 1,
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'SHIPMENT_ALREADY_DELIVERED');
});

it('rejects decimal shipment quantities and unknown providers', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 1, 'qty');
    $manager = $this->createUserWithRole(Role::OrderManager);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('qty-d'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1.5]],
        ])->assertStatus(422);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('qty-n'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => -1]],
        ])->assertStatus(422);

    $this->actingAs($manager, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('qty-u'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'not_a_carrier',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertStatus(404)
        ->assertJsonPath('error.code', 'SHIPMENT_PROVIDER_UNKNOWN');
});

it('forbids a catalog manager from creating shipments', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $paid = FulfillmentFixtures::paidPickupOrder($fixture['variant']->id, 1, 'cat');
    $catalog = $this->createUserWithRole(Role::CatalogManager);

    $this->actingAs($catalog, 'web')
        ->withHeaders(FulfillmentFixtures::adminHeaders('nope'))
        ->postJson('/api/v1/admin/orders/'.$paid['order_id'].'/shipments', [
            'provider_code' => 'manual',
            'items' => [['order_item_id' => $paid['item_id'], 'quantity' => 1]],
        ])->assertForbidden();
});
