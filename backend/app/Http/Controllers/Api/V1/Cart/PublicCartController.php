<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cart;

use App\Domains\Cart\Actions\AddCartItemAction;
use App\Domains\Cart\Actions\ClearCartAction;
use App\Domains\Cart\Actions\GetCartAction;
use App\Domains\Cart\Actions\MergeGuestCartAction;
use App\Domains\Cart\Actions\RemoveCartItemAction;
use App\Domains\Cart\Actions\UpdateCartItemAction;
use App\Domains\Cart\DTOs\AddCartItemData;
use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\DTOs\RemoveCartItemData;
use App\Domains\Cart\DTOs\UpdateCartItemData;
use App\Domains\Cart\Exceptions\CartIdempotencyReplayException;
use App\Domains\Cart\Services\CartIdempotencyService;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cart\AddCartItemRequest;
use App\Http\Requests\Api\V1\Cart\CartMutationRequest;
use App\Http\Requests\Api\V1\Cart\UpdateCartItemRequest;
use App\Http\Support\CartActorFactory;
use App\Http\Support\CartCatalogHydrator;
use App\Http\Support\GuestCartCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCartController extends Controller
{
    public function __construct(
        private readonly CartActorFactory $actors,
        private readonly CartCatalogHydrator $hydrator,
        private readonly GuestCartCookie $cookie,
        private readonly CartIdempotencyService $idempotency,
        private readonly GetCartAction $getCart,
        private readonly AddCartItemAction $addItem,
        private readonly UpdateCartItemAction $updateItem,
        private readonly RemoveCartItemAction $removeItem,
        private readonly ClearCartAction $clearCart,
        private readonly MergeGuestCartAction $mergeCart,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $actor = $this->actors->fromRequest($request);
        $result = $this->getCart->execute($actor);

        return $this->respond($request, $result, ['data' => $this->hydrator->present($result->cart, $actor->locale)]);
    }

    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->cartIdempotencyKey(),
            'cart.items.store',
            $request->cartVersion(),
        );
        $payload = [
            'variant_id' => $request->variantId(),
            'product_id' => $request->productId(),
            'quantity' => $request->quantity(),
        ];

        return $this->mutating($request, $actor, $payload, fn () => $this->addItem->execute(new AddCartItemData(
            actor: $actor,
            variantId: $request->variantId(),
            quantity: $request->quantity(),
            productId: $request->productId(),
        )));
    }

    public function updateItem(UpdateCartItemRequest $request, string $cartItemPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->cartIdempotencyKey(),
            'cart.items.update',
            $request->cartVersion(),
        );
        $payload = [
            'item_id' => $cartItemPublicId,
            'quantity' => $request->quantity(),
        ];

        return $this->mutating($request, $actor, $payload, fn () => $this->updateItem->execute(new UpdateCartItemData(
            actor: $actor,
            itemPublicId: $cartItemPublicId,
            quantity: $request->quantity(),
        )));
    }

    public function destroyItem(CartMutationRequest $request, string $cartItemPublicId): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->cartIdempotencyKey(),
            'cart.items.destroy',
            $request->cartVersion(),
        );
        $payload = ['item_id' => $cartItemPublicId];

        return $this->mutating($request, $actor, $payload, fn () => $this->removeItem->execute(new RemoveCartItemData(
            actor: $actor,
            itemPublicId: $cartItemPublicId,
        )));
    }

    public function destroy(CartMutationRequest $request): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->cartIdempotencyKey(),
            'cart.destroy',
            $request->cartVersion(),
        );

        return $this->mutating($request, $actor, ['clear' => true], fn () => $this->clearCart->execute($actor));
    }

    public function merge(CartMutationRequest $request): JsonResponse
    {
        $actor = $this->actors->fromRequest(
            $request,
            $request->cartIdempotencyKey(),
            'cart.merge',
            $request->cartVersion(),
        );

        return $this->mutating($request, $actor, ['merge' => true], fn () => $this->mergeCart->execute($actor));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): CartMutationResultData  $callback
     */
    private function mutating(Request $request, CartActorData $actor, array $payload, callable $callback): JsonResponse
    {
        $record = null;
        try {
            $record = $this->idempotency->begin($actor, $payload);
            $result = $callback();
        } catch (CartIdempotencyReplayException $replay) {
            return response()->json($replay->body(), $replay->statusCode())
                ->header('Cache-Control', 'private, no-store')
                ->header(CorrelationId::HEADER, (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE));
        }

        $payload = ['data' => $this->hydrator->present($result->cart, $actor->locale)];
        if ($record !== null) {
            $this->idempotency->complete($record, $payload);
        }

        return $this->respond($request, $result, $payload);
    }

    /**
     * @param  array{data: array<string, mixed>}  $payload
     */
    private function respond(Request $request, CartMutationResultData $result, array $payload): JsonResponse
    {
        $response = response()->json(
            $payload,
            200,
            [
                'Cache-Control' => 'private, no-store',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        )->header(
            CorrelationId::HEADER,
            (string) $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
        );

        $this->cookie->apply($response, $result->issuedGuestToken, $result->forgetGuestCookie);

        return $response;
    }
}
