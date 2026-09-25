<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ReconcilePaymentsAction;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaTokenProvider;
use App\Domains\Payments\Services\PaymentProviderRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\BogPaymentFixtures;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

beforeEach(function (): void {
    BogPaymentFixtures::enable();
});

it('creates a Bank of Georgia hosted redirect from the immutable order total', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog1');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog1-c', 'Accept' => 'application/json', 'X-Locale' => 'ka'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ]);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'requires_action')
        ->assertJsonPath('data.amount.amount_minor', $pending['grand_total_minor'])
        ->assertJsonPath('data.action.type', 'redirect');
    expect((string) $created->json('data.action.url'))->toStartWith('https://payment.bog.ge/');

    $request = BogPaymentFixtures::lastCreateOrderRequest();
    expect($request)->not->toBeNull();
    $json = $request->body();
    expect($json)->toContain('"capture":"automatic"')
        ->and($json)->toContain('"external_order_id":"'.$created->json('data.id').'"')
        ->and($json)->toContain('"payment_method":["card"]')
        ->and($request->header('Idempotency-Key')[0] ?? null)->toBe($created->json('data.id'))
        ->and($request->header('Accept-Language')[0] ?? null)->toBe('ka')
        ->and($json)->not->toContain((string) config('payments.providers.bog.client_secret'));
});

it('confirms payment from a valid RSA callback and commits inventory once', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog2');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog2-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $major = sprintf('%d.%02d', intdiv($attempt->amount_minor, 100), $attempt->amount_minor % 100);
    $signed = BogPaymentFixtures::signedCallback(
        (string) $attempt->provider_payment_id,
        $attempt->public_id,
        'completed',
        requestAmount: $major,
        currency: $attempt->currency,
    );

    $first = BogPaymentFixtures::postCallback($signed['body'], $signed['headers']);
    $first->assertOk()->assertJsonPath('received', true);

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::Confirmed)
        ->and($order?->payment_status)->toBe(PaymentStatus::Paid)
        ->and(InventoryReservation::query()->where('reference_id', $pending['order_id'])->where('status', InventoryReservationStatus::Committed)->count())->toBe(1);

    $ledger = InventoryLedgerEntry::query()->count();
    BogPaymentFixtures::postCallback($signed['body'], $signed['headers'])->assertOk();
    expect(InventoryLedgerEntry::query()->count())->toBe($ledger);
});

it('rejects an invalid callback signature without changing payment state', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog3');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog3-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $signed = BogPaymentFixtures::signedCallback((string) $attempt->provider_payment_id, $attempt->public_id);
    $signed['headers']['Callback-Signature'] = base64_encode('invalid');
    BogPaymentFixtures::postCallback($signed['body'], $signed['headers'])->assertStatus(400);

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->payment_status)->not->toBe(PaymentStatus::Paid);
});

it('sends amount mismatch to manual review', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog4');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog4-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $signed = BogPaymentFixtures::signedCallback(
        (string) $attempt->provider_payment_id,
        $attempt->public_id,
        'completed',
        requestAmount: '0.01',
        currency: $attempt->currency,
    );
    BogPaymentFixtures::postCallback($signed['body'], $signed['headers'])->assertOk();
    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and($order?->status)->toBe(OrderStatus::ManualReview);
});

it('maps a rejected callback to a failed attempt that can be retried', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog5');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog5-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $major = sprintf('%d.%02d', intdiv($attempt->amount_minor, 100), $attempt->amount_minor % 100);
    $signed = BogPaymentFixtures::signedCallback(
        (string) $attempt->provider_payment_id,
        $attempt->public_id,
        'rejected',
        requestAmount: $major,
        currency: $attempt->currency,
    );
    BogPaymentFixtures::postCallback($signed['body'], $signed['headers'])->assertOk();

    $attempt->refresh();
    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($attempt->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($order?->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and(InventoryReservation::query()->where('reference_id', $pending['order_id'])->where('status', InventoryReservationStatus::Active)->count())->toBe(1);
});

it('reconciles a lost callback through payment details', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog6');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog6-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $major = sprintf('%d.%02d', intdiv($attempt->amount_minor, 100), $attempt->amount_minor % 100);
    BogPaymentFixtures::$detailsExternalOrderId = $attempt->public_id;
    BogPaymentFixtures::$detailsAmountMajor = $major;
    BogPaymentFixtures::$detailsCurrency = $attempt->currency;
    $attempt->updated_at = now()->subMinutes(5);
    $attempt->save();

    $result = app(ReconcilePaymentsAction::class)
        ->execute($attempt->public_id, 'bog');
    expect($result['succeeded'])->toBe(1);
    $this->artisan('payments:reconcile', ['--provider' => 'bog', '--attempt' => $attempt->public_id])
        ->assertSuccessful();
    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->payment_status)->toBe(PaymentStatus::Paid);
});

it('caches the OAuth token between Bank of Georgia requests', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    app(BankOfGeorgiaTokenProvider::class)->forget();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog7');
    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog7-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    $oauth = Http::recorded()->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'openid-connect/token'));
    expect($oauth)->toHaveCount(1);
});

it('marks an attempt unknown when create-order times out', function (): void {
    Http::fake([
        'https://oauth2.bog.ge/*' => Http::response([
            'access_token' => 'bog-access-token-test',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        'https://api.bog.ge/payments/v1/ecommerce/orders' => function () {
            throw new ConnectionException('timed out');
        },
    ]);
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog8');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog8-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ]);

    $created->assertSuccessful();
    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->first();
    expect($attempt?->status)->toBe(PaymentAttemptStatus::Unknown);
});

it('lists Bank of Georgia only when the provider is ready', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog9');
    $ok = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->getJson('/api/v1/payment-methods?order_id='.$pending['order_id'])
        ->assertOk();
    expect(collect($ok->json('data'))->pluck('code')->all())->toContain('bog_hosted_card');

    config()->set('payments.providers.bog.enabled', false);
    config()->set('payments.methods.bog_hosted_card.is_enabled', false);
    $hidden = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->getJson('/api/v1/payment-methods?order_id='.$pending['order_id'])
        ->assertOk();
    expect(collect($hidden->json('data'))->pluck('code')->all())->not->toContain('bog_hosted_card');
});

it('does not expose secrets through provider diagnostics', function (): void {
    $health = app(PaymentProviderRegistry::class)->health('bog');
    $json = json_encode($health);
    expect($json)->not->toContain('bog-test-secret')
        ->and($json)->not->toContain('bog-test-client');
});

it('sends a completed callback after expiration to manual review', function (): void {
    BogPaymentFixtures::fakeHappyPath();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'bog10');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'bog10-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'bog_hosted_card',
        ])->assertCreated();

    Order::query()->where('public_id', $pending['order_id'])->update([
        'reservation_expires_at' => now()->subMinute(),
    ]);
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $major = sprintf('%d.%02d', intdiv($attempt->amount_minor, 100), $attempt->amount_minor % 100);
    $signed = BogPaymentFixtures::signedCallback(
        (string) $attempt->provider_payment_id,
        $attempt->public_id,
        'completed',
        requestAmount: $major,
        currency: $attempt->currency,
    );
    BogPaymentFixtures::postCallback($signed['body'], $signed['headers'])->assertOk();

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::ManualReview)
        ->and($order?->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and(InventoryReservation::query()->where('reference_id', $pending['order_id'])->where('status', InventoryReservationStatus::Committed)->count())->toBe(0);
});
