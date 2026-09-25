<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;

final class FulfillmentFixtures
{
    /**
     * @return array{
     *     order_id: string,
     *     order_cookie: string,
     *     item_id: string,
     *     quantity: int,
     *     warehouse_code: string
     * }
     */
    public static function paidPickupOrder(int $variantId, int $quantity = 2, string $prefix = 'ff'): array
    {
        $quoted = OrderFixtures::quotedGuestCheckout($variantId, $quantity, $prefix);
        $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
            ->withHeaders(OrderFixtures::headers($prefix.'-ord'))
            ->postJson('/api/v1/orders', [
                'checkout_session_id' => $quoted['session_id'],
                'quote_id' => $quoted['quote_id'],
                'checkout_version' => $quoted['checkout_version'],
            ])->assertCreated();

        $orderId = (string) $created->json('data.id');
        $orderCookie = (string) OrderFixtures::guestOrderCookie($created);

        $attempt = test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->withHeaders(['Idempotency-Key' => $prefix.'-pay', 'Accept' => 'application/json'])
            ->postJson('/api/v1/orders/'.$orderId.'/payment-attempts', [
                'payment_method_code' => 'test_hosted_redirect',
            ])->assertCreated();

        test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->postJson('/api/v1/payments/test/attempts/'.$attempt->json('data.id').'/simulate', [
                'outcome' => 'success',
            ])->assertOk();

        $order = test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->getJson('/api/v1/orders/'.$orderId)
            ->assertOk();

        $warehouseId = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $orderId)
            ->value('warehouse_id');
        $warehouse = Warehouse::query()->find($warehouseId);

        return [
            'order_id' => $orderId,
            'order_cookie' => $orderCookie,
            'item_id' => (string) $order->json('data.items.0.id'),
            'quantity' => $quantity,
            'warehouse_code' => (string) ($warehouse?->code ?? ''),
        ];
    }

    /**
     * @return array{
     *     order_id: string,
     *     order_cookie: string,
     *     item_id: string,
     *     quantity: int,
     *     warehouse_code: string
     * }
     */
    public static function paidDeliveryOrder(int $variantId, int $quantity = 2, string $prefix = 'dlv'): array
    {
        $quoted = OrderFixtures::quotedGuestDeliveryCheckout($variantId, $quantity, $prefix);
        $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
            ->withHeaders(OrderFixtures::headers($prefix.'-ord'))
            ->postJson('/api/v1/orders', [
                'checkout_session_id' => $quoted['session_id'],
                'quote_id' => $quoted['quote_id'],
                'checkout_version' => $quoted['checkout_version'],
            ])->assertCreated();

        $orderId = (string) $created->json('data.id');
        $orderCookie = (string) OrderFixtures::guestOrderCookie($created);

        $attempt = test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->withHeaders(['Idempotency-Key' => $prefix.'-pay', 'Accept' => 'application/json'])
            ->postJson('/api/v1/orders/'.$orderId.'/payment-attempts', [
                'payment_method_code' => 'test_hosted_redirect',
            ])->assertCreated();

        test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->postJson('/api/v1/payments/test/attempts/'.$attempt->json('data.id').'/simulate', [
                'outcome' => 'success',
            ])->assertOk();

        $order = test()->withUnencryptedCookie((string) config('order.cookie.name'), $orderCookie)
            ->getJson('/api/v1/orders/'.$orderId)
            ->assertOk();

        $warehouseId = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $orderId)
            ->value('warehouse_id');
        $warehouse = Warehouse::query()->find($warehouseId);

        return [
            'order_id' => $orderId,
            'order_cookie' => $orderCookie,
            'item_id' => (string) $order->json('data.items.0.id'),
            'quantity' => $quantity,
            'warehouse_code' => (string) ($warehouse?->code ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function adminHeaders(string $key): array
    {
        return [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
            'X-Locale' => 'en',
        ];
    }
}
