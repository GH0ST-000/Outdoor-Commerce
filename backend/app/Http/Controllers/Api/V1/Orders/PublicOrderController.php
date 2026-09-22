<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Domains\Orders\Actions\CancelOrderAction;
use App\Domains\Orders\Actions\CreateOrderFromQuoteAction;
use App\Domains\Orders\Actions\GetOrderAction;
use App\Domains\Orders\DTOs\OrderMutationResultData;
use App\Domains\Orders\Exceptions\OrderIdempotencyReplayException;
use App\Domains\Orders\Services\OrderIdempotencyService;
use App\Domains\Orders\Services\OrderPresenter;
use App\Domains\Payments\Actions\GetPaymentAttemptAction;
use App\Domains\Payments\Services\PaymentPresenter;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CancelOrderRequest;
use App\Http\Requests\Api\V1\Orders\CreateOrderRequest;
use App\Http\Support\GuestCartCookie;
use App\Http\Support\GuestOrderCookie;
use App\Http\Support\OrderActorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicOrderController extends Controller
{
    public function __construct(
        private readonly OrderActorFactory $actors,
        private readonly OrderIdempotencyService $idempotency,
        private readonly OrderPresenter $presenter,
        private readonly CreateOrderFromQuoteAction $create,
        private readonly GetOrderAction $get,
        private readonly CancelOrderAction $cancel,
        private readonly GetPaymentAttemptAction $paymentAttempts,
        private readonly PaymentPresenter $payments,
        private readonly GuestOrderCookie $orderCookie,
        private readonly GuestCartCookie $cartCookie,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->orderIdempotencyKey(),
            'orders.store',
            $request->checkoutVersion(),
        );

        $record = null;
        try {
            $record = $this->idempotency->begin($actor, [
                'checkout_session_id' => $request->checkoutSessionId(),
                'quote_id' => $request->quoteId(),
                'checkout_version' => $request->checkoutVersion(),
            ]);
            $result = $this->create->execute($actor, $request->checkoutSessionId(), $request->quoteId());
        } catch (OrderIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode())
                ->header('Cache-Control', 'private, no-store')
                ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
        }

        $status = $result->created ? 201 : 200;
        $body = $this->payments->enrichOrder(
            $this->presenter->present($result->order),
            $result->order,
            $this->paymentAttempts->currentForOrder($result->order),
        );
        if ($record !== null) {
            $this->idempotency->complete($record, $body, $status);
        }

        return $this->respond($request, $body, $status, $result);
    }

    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $order = $this->get->execute($actor, $orderPublicId);

        return $this->respond($request, $this->payments->enrichOrder(
            $this->presenter->present($order),
            $order,
            $this->paymentAttempts->currentForOrder($order),
        ), 200);
    }

    public function cancel(CancelOrderRequest $request, string $orderPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->orderIdempotencyKey(),
            'orders.cancel',
        );

        $record = null;
        try {
            $record = $this->idempotency->begin($actor, ['order_id' => $orderPublicId, 'cancel' => true]);
            $order = $this->cancel->execute($actor, $orderPublicId);
        } catch (OrderIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode())
                ->header('Cache-Control', 'private, no-store')
                ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
        }

        $body = $this->payments->enrichOrder(
            $this->presenter->present($order),
            $order,
            $this->paymentAttempts->currentForOrder($order),
        );
        if ($record !== null) {
            $this->idempotency->complete($record, $body, 200);
        }

        return $this->respond($request, $body, 200);
    }

    /**
     * @param  array{data: array<string, mixed>}  $payload
     */
    private function respond(
        Request $request,
        array $payload,
        int $status,
        ?OrderMutationResultData $result = null,
    ): JsonResponse {
        $response = response()->json(
            $payload,
            $status,
            ['Cache-Control' => 'private, no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(
            CorrelationId::HEADER,
            (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
        );

        if ($result !== null) {
            $this->orderCookie->apply($response, $result->issuedOrderToken, false);
            if ($result->forgetGuestCartCookie) {
                $this->cartCookie->forget($response);
            }
        }

        return $response;
    }
}
