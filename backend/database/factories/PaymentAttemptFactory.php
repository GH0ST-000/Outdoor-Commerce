<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'provider' => PaymentProviderCode::Test->value,
            'payment_method_code' => 'test_hosted_redirect',
            'status' => PaymentAttemptStatus::Created,
            'amount_minor' => 10000,
            'currency' => 'GEL',
            'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
            'request_fingerprint' => hash('sha256', (string) Str::uuid()),
            'version' => 1,
        ];
    }
}
