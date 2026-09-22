<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\CancelPaymentAttemptAction;
use App\Domains\Payments\Actions\CreatePaymentAttemptAction;
use App\Domains\Payments\Actions\GetPaymentAttemptAction;
use App\Domains\Payments\DTOs\CreatePaymentAttemptData;
use App\Domains\Payments\Exceptions\PaymentIdempotencyReplayException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\PaymentIdempotencyService;
use App\Domains\Payments\Services\PaymentPresenter;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\CancelPaymentAttemptRequest;
use App\Http\Requests\Api\V1\Payments\CreatePaymentAttemptRequest;
use App\Http\Support\OrderActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicPaymentAttemptController extends Controller
{
    public function __construct(
        private readonly OrderActorFactory $actors,
        private readonly PaymentIdempotencyService $idempotency,
        private readonly PaymentPresenter $presenter,
        private readonly CreatePaymentAttemptAction $create,
        private readonly GetPaymentAttemptAction $get,
        private readonly CancelPaymentAttemptAction $cancel,
    ) {}

    public function store(CreatePaymentAttemptRequest $request, string $orderPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->paymentIdempotencyKey(),
            'payments.create',
        );

        $record = null;
        try {
            $record = $this->idempotency->begin($actor, [
                'order_id' => $orderPublicId,
                'payment_method_code' => $request->paymentMethodCode(),
            ]);
            $attempt = $this->create->execute($actor, new CreatePaymentAttemptData(
                orderPublicId: $orderPublicId,
                paymentMethodCode: $request->paymentMethodCode(),
                orderVersion: $request->orderVersion(),
            ));
        } catch (PaymentIdempotencyReplayException $replay) {
            return $this->json($request, $replay->body(), $replay->statusCode());
        }

        $order = $this->orderOf($attempt);
        $body = $this->presenter->presentAttempt($attempt, $order);
        if ($record !== null) {
            $this->idempotency->complete($record, $body, 201);
        }

        return $this->json($request, $body, 201);
    }

    public function current(Request $request, string $orderPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $attempt = $this->get->current($actor, $orderPublicId);
        if ($attempt === null) {
            return $this->json($request, ['data' => null], 200);
        }

        return $this->json($request, $this->presenter->presentAttempt($attempt, $this->orderOf($attempt)), 200);
    }

    public function show(Request $request, string $paymentAttemptPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $attempt = $this->get->execute($actor, $paymentAttemptPublicId);

        return $this->json($request, $this->presenter->presentAttempt($attempt, $this->orderOf($attempt)), 200);
    }

    public function cancel(CancelPaymentAttemptRequest $request, string $paymentAttemptPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->paymentIdempotencyKey(),
            'payments.cancel',
        );

        $record = null;
        try {
            $record = $this->idempotency->begin($actor, [
                'attempt_id' => $paymentAttemptPublicId,
                'cancel' => true,
            ]);
            $attempt = $this->cancel->execute($actor, $paymentAttemptPublicId);
        } catch (PaymentIdempotencyReplayException $replay) {
            return $this->json($request, $replay->body(), $replay->statusCode());
        }

        $body = $this->presenter->presentAttempt($attempt, $this->orderOf($attempt));
        if ($record !== null) {
            $this->idempotency->complete($record, $body, 200);
        }

        return $this->json($request, $body, 200);
    }

    private function orderOf(PaymentAttempt $attempt): Order
    {
        $order = $attempt->order()->first();
        if ($order === null) {
            $order = Order::query()->whereKey($attempt->order_id)->firstOrFail();
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(Request $request, array $payload, int $status): JsonResponse
    {
        return response()->json(
            $payload,
            $status,
            ['Cache-Control' => 'private, no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
