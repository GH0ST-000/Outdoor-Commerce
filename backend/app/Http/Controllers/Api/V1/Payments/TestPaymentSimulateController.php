<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payments;

use App\Domains\Payments\Actions\SimulateTestPaymentAction;
use App\Domains\Payments\Services\PaymentPresenter;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\SimulateTestPaymentRequest;
use App\Http\Support\OrderActorFactory;
use Illuminate\Http\JsonResponse;

final class TestPaymentSimulateController extends Controller
{
    public function __construct(
        private readonly OrderActorFactory $actors,
        private readonly SimulateTestPaymentAction $simulate,
        private readonly PaymentPresenter $presenter,
    ) {}

    public function store(SimulateTestPaymentRequest $request, string $paymentAttemptPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $attempt = $this->simulate->execute($actor, $paymentAttemptPublicId, $request->outcome());
        $order = $attempt->order()->firstOrFail();

        return response()->json(
            $this->presenter->presentAttempt($attempt, $order),
            200,
            ['Cache-Control' => 'private, no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
    }
}
