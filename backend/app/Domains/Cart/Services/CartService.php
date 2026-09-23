<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\DTOs\AddCartItemData;
use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\DTOs\RemoveCartItemData;
use App\Domains\Cart\DTOs\UpdateCartItemData;
use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Events\CartCleared;
use App\Domains\Cart\Exceptions\CartException;
use App\Domains\Cart\Exceptions\CartVersionConflictException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Cart\Support\CartDeadlockRetry;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class CartService
{
    public function __construct(
        private readonly CartResolver $resolver,
        private readonly CartValidationService $validation,
        private readonly CartItemService $items,
        private readonly CartPresenter $presenter,
        private readonly CartDeadlockRetry $retry,
        private readonly CartLogger $logger,
        private readonly PublicCatalogPricing $pricing,
        private readonly Clock $clock,
    ) {}

    public function get(CartActorData $actor): CartMutationResultData
    {
        $cart = $this->resolver->findActive($actor);
        if ($cart === null) {
            return new CartMutationResultData($this->presenter->empty($actor->currency));
        }

        $snapshot = $this->presenter->present($cart, $actor->locale, $actor->priceListId);

        return new CartMutationResultData($snapshot);
    }

    public function addItem(AddCartItemData $data): CartMutationResultData
    {
        $started = microtime(true);

        $result = $this->retry->run(function () use ($data): CartMutationResultData {
            return DB::transaction(function () use ($data): CartMutationResultData {
                $resolved = $this->resolver->resolveOrCreate($data->actor);
                $cart = $this->resolver->lockActive($resolved['cart']);
                $this->assertMutable($cart);
                $this->validation->assertCurrency($data->actor, $cart->currency);
                $this->assertVersion($cart, $data->actor);

                $variant = $this->validation->sellableVariant(
                    $data->variantId,
                    $data->productId,
                    $data->actor->currency,
                    $data->actor->priceListId,
                );
                $quote = $this->pricing->quoteVariant($variant->id, $data->actor->priceListId);
                if ($quote === null) {
                    throw CartException::productUnavailable();
                }

                $this->items->addOrIncrease($cart, $variant, $data->quantity, $quote, $data->actor->locale);
                $cart->version = $cart->version + 1;
                $cart->save();

                $snapshot = $this->presenter->present($cart->fresh() ?? $cart, $data->actor->locale, $data->actor->priceListId);

                return new CartMutationResultData($snapshot, $resolved['issued_guest_token']);
            });
        });

        $this->logger->info('item_added', [
            'cart_public_id' => $result->cart->publicId,
            'variant_id' => $data->variantId,
            'quantity' => $data->quantity,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $result;
    }

    public function updateItem(UpdateCartItemData $data): CartMutationResultData
    {
        return $this->retry->run(function () use ($data): CartMutationResultData {
            return DB::transaction(function () use ($data): CartMutationResultData {
                $cart = $this->requireActive($data->actor);
                $this->assertVersion($cart, $data->actor);
                $item = $this->ownedItem($cart, $data->itemPublicId);
                $this->items->setQuantity($cart, $item, $data->quantity);
                $cart->version = $cart->version + 1;
                $cart->save();

                return new CartMutationResultData(
                    $this->presenter->present($cart->fresh() ?? $cart, $data->actor->locale, $data->actor->priceListId),
                );
            });
        });
    }

    public function removeItem(RemoveCartItemData $data): CartMutationResultData
    {
        return $this->retry->run(function () use ($data): CartMutationResultData {
            return DB::transaction(function () use ($data): CartMutationResultData {
                $cart = $this->requireActive($data->actor);
                $this->assertVersion($cart, $data->actor);
                $item = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('public_id', $data->itemPublicId)
                    ->lockForUpdate()
                    ->first();

                if ($item !== null) {
                    $this->items->remove($cart, $item);
                    $cart->version = $cart->version + 1;
                    $cart->save();
                }

                return new CartMutationResultData(
                    $this->presenter->present($cart->fresh() ?? $cart, $data->actor->locale, $data->actor->priceListId),
                );
            });
        });
    }

    public function clear(CartActorData $actor): CartMutationResultData
    {
        return $this->retry->run(function () use ($actor): CartMutationResultData {
            return DB::transaction(function () use ($actor): CartMutationResultData {
                $cart = $this->resolver->findActive($actor);
                if ($cart === null) {
                    return new CartMutationResultData($this->presenter->empty($actor->currency));
                }

                $cart = $this->resolver->lockActive($cart);
                $this->assertMutable($cart);
                $this->assertVersion($cart, $actor);
                $removed = CartItem::query()->where('cart_id', $cart->id)->count();
                CartItem::query()->where('cart_id', $cart->id)->delete();
                $cart->version = $cart->version + 1;
                $cart->save();

                DB::afterCommit(function () use ($cart, $removed): void {
                    event(new CartCleared($cart->public_id, $removed));
                });

                return new CartMutationResultData(
                    $this->presenter->present($cart->fresh() ?? $cart, $actor->locale, $actor->priceListId),
                );
            });
        });
    }

    private function requireActive(CartActorData $actor): Cart
    {
        $cart = $this->resolver->findActive($actor);
        if ($cart === null) {
            throw CartException::itemNotFound();
        }

        $locked = $this->resolver->lockActive($cart);
        $this->assertMutable($locked);
        $this->validation->assertCurrency($actor, $locked->currency);

        return $locked;
    }

    private function ownedItem(Cart $cart, string $publicId): CartItem
    {
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('public_id', $publicId)
            ->lockForUpdate()
            ->first();

        if ($item === null) {
            throw CartException::itemNotFound();
        }

        return $item;
    }

    private function assertMutable(Cart $cart): void
    {
        if ($cart->status !== CartStatus::Active) {
            throw CartException::notMutable();
        }

        if ($cart->expires_at !== null && $cart->expires_at->lessThan($this->clock->now())) {
            throw CartException::notMutable();
        }
    }

    private function assertVersion(Cart $cart, CartActorData $actor): void
    {
        if ($actor->expectedVersion === null) {
            return;
        }

        if ($actor->expectedVersion !== $cart->version) {
            $snapshot = $this->presenter->present($cart, $actor->locale, $actor->priceListId, false);
            throw new CartVersionConflictException($snapshot);
        }
    }
}
