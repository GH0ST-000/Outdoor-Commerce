<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Domains\Checkout\Actions\CancelCheckoutSessionAction;
use App\Domains\Checkout\Actions\CreateCheckoutQuoteAction;
use App\Domains\Checkout\Actions\CreateCheckoutSessionAction;
use App\Domains\Checkout\Actions\GetCheckoutSessionAction;
use App\Domains\Checkout\Actions\UpdateCheckoutAddressAction;
use App\Domains\Checkout\Actions\UpdateCheckoutContactAction;
use App\Domains\Checkout\Actions\UpdateCheckoutFulfillmentAction;
use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\DTOs\CheckoutMutationResultData;
use App\Domains\Checkout\Exceptions\CheckoutIdempotencyReplayException;
use App\Domains\Checkout\Services\CheckoutIdempotencyService;
use App\Domains\Checkout\Services\CheckoutSessionService;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Checkout\CancelCheckoutSessionRequest;
use App\Http\Requests\Api\V1\Checkout\CreateCheckoutQuoteRequest;
use App\Http\Requests\Api\V1\Checkout\CreateCheckoutSessionRequest;
use App\Http\Requests\Api\V1\Checkout\UpdateCheckoutAddressRequest;
use App\Http\Requests\Api\V1\Checkout\UpdateCheckoutContactRequest;
use App\Http\Requests\Api\V1\Checkout\UpdateCheckoutFulfillmentRequest;
use App\Http\Support\CheckoutActorFactory;
use App\Http\Support\CheckoutCatalogHydrator;
use App\Http\Support\GuestCartCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutActorFactory $actors,
        private readonly CheckoutCatalogHydrator $catalog,
        private readonly GuestCartCookie $cookie,
        private readonly CheckoutIdempotencyService $idempotency,
        private readonly CheckoutSessionService $sessions,
        private readonly CreateCheckoutSessionAction $createSession,
        private readonly GetCheckoutSessionAction $getSession,
        private readonly UpdateCheckoutContactAction $updateContact,
        private readonly UpdateCheckoutAddressAction $updateAddress,
        private readonly UpdateCheckoutFulfillmentAction $updateFulfillment,
        private readonly CreateCheckoutQuoteAction $createQuote,
        private readonly CancelCheckoutSessionAction $cancelSession,
    ) {}

    public function store(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $actor = $this->actors->fromRequest($request, $request->checkoutIdempotencyKey(), 'checkout.sessions.store');

        return $this->mutating($request, $actor, ['create' => true], fn () => $this->createSession->execute($actor));
    }

    public function show(Request $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $result = $this->getSession->execute($actor, $checkoutSessionPublicId);

        return $this->respond($request, $actor, $result);
    }

    public function updateContact(UpdateCheckoutContactRequest $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->checkoutIdempotencyKey(),
            'checkout.contact',
            $request->checkoutVersion(),
        );

        return $this->mutating(
            $request,
            $actor,
            ['contact' => $request->only(['first_name', 'last_name', 'email', 'phone', 'customer_note', 'checkout_version'])],
            fn () => $this->updateContact->execute($actor, $checkoutSessionPublicId, $request->contactData()),
        );
    }

    public function updateAddress(UpdateCheckoutAddressRequest $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->checkoutIdempotencyKey(),
            'checkout.address',
            $request->checkoutVersion(),
        );

        return $this->mutating(
            $request,
            $actor,
            ['address_fingerprint' => $request->only([
                'country_code',
                'region',
                'municipality_or_city',
                'street',
                'house_number',
                'postal_code',
                'checkout_version',
            ])],
            fn () => $this->updateAddress->execute($actor, $checkoutSessionPublicId, $request->addressData()),
        );
    }

    public function updateFulfillment(UpdateCheckoutFulfillmentRequest $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->checkoutIdempotencyKey(),
            'checkout.fulfillment',
            $request->checkoutVersion(),
        );

        return $this->mutating(
            $request,
            $actor,
            [
                'method_code' => $request->methodCode(),
                'pickup_location_id' => $request->pickupLocationId(),
                'checkout_version' => $request->checkoutVersion(),
            ],
            fn () => $this->updateFulfillment->execute(
                $actor,
                $checkoutSessionPublicId,
                $request->methodCode(),
                $request->pickupLocationId(),
            ),
        );
    }

    public function quote(CreateCheckoutQuoteRequest $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->checkoutIdempotencyKey(),
            'checkout.quote',
            $request->checkoutVersion(),
            $request->cartVersion(),
        );

        return $this->mutating(
            $request,
            $actor,
            [
                'checkout_version' => $request->checkoutVersion(),
                'cart_version' => $request->cartVersion(),
            ],
            fn () => $this->createQuote->execute($actor, $checkoutSessionPublicId),
        );
    }

    public function destroy(CancelCheckoutSessionRequest $request, string $checkoutSessionPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->checkoutIdempotencyKey(),
            'checkout.cancel',
            $request->checkoutVersion(),
        );

        return $this->mutating(
            $request,
            $actor,
            ['cancel' => true, 'checkout_version' => $request->checkoutVersion()],
            fn () => $this->cancelSession->execute($actor, $checkoutSessionPublicId),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): CheckoutMutationResultData  $callback
     */
    private function mutating(Request $request, CheckoutActorData $actor, array $payload, callable $callback): JsonResponse
    {
        $record = null;
        try {
            $record = $this->idempotency->begin($actor, $payload);
            $result = $callback();
        } catch (CheckoutIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode())
                ->header('Cache-Control', 'private, no-store')
                ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
        }

        $body = $this->catalog->present($this->sessions->present($result->session, $actor), $actor->locale());
        if ($record !== null) {
            $this->idempotency->complete($record, $body);
        }

        return $this->respond($request, $actor, $result, $body);
    }

    /**
     * @param  array{data: array<string, mixed>}|null  $payload
     */
    private function respond(
        Request $request,
        CheckoutActorData $actor,
        CheckoutMutationResultData $result,
        ?array $payload = null,
    ): JsonResponse {
        $payload ??= $this->catalog->present($this->sessions->present($result->session, $actor), $actor->locale());
        $response = response()->json(
            $payload,
            200,
            ['Cache-Control' => 'private, no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(
            CorrelationId::HEADER,
            (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
        );

        $this->cookie->apply($response, $result->issuedGuestToken, false);

        return $response;
    }
}
