<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'public_id' => (string) Str::uuid(),
            'order_number' => 'ORD-'.$now->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6)),
            'user_id' => null,
            'checkout_session_id' => CheckoutSession::factory(),
            'checkout_quote_id' => CheckoutQuote::factory(),
            'cart_id' => Cart::factory(),
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Unpaid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'currency' => 'GEL',
            'items_subtotal_minor' => 10000,
            'discount_total_minor' => 0,
            'delivery_total_minor' => 0,
            'tax_total_minor' => 0,
            'grand_total_minor' => 10000,
            'price_includes_tax' => true,
            'customer_email' => 'nino@example.com',
            'customer_phone' => '+995555123456',
            'customer_first_name' => 'ნინო',
            'customer_last_name' => 'ბერიძე',
            'customer_note' => null,
            'fulfillment_method_code' => 'store_pickup',
            'fulfillment_method_name' => 'Store pickup',
            'quote_revision' => 1,
            'quote_fingerprint' => hash('sha256', (string) Str::uuid()),
            'contact_snapshot' => [
                'first_name' => 'ნინო',
                'last_name' => 'ბერიძე',
                'email' => 'nino@example.com',
                'phone' => '+995555123456',
                'customer_note' => null,
            ],
            'shipping_address_snapshot' => null,
            'billing_address_snapshot' => null,
            'fulfillment_snapshot' => [
                'method_code' => 'store_pickup',
                'method_type' => 'store_pickup',
                'localized_name' => 'Store pickup',
                'delivery_amount_minor' => 0,
                'pickup_location' => null,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
            ],
            'access_token_hash' => null,
            'reservation_expires_at' => $now->addMinutes(20),
            'placed_at' => $now,
            'version' => 1,
        ];
    }
}
