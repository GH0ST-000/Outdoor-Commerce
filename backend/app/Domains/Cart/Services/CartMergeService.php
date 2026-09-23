<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\DTOs\CartIssueData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\DTOs\CartSnapshotData;
use App\Domains\Cart\Enums\CartIssueCode;
use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Events\CartMerged;
use App\Domains\Cart\Exceptions\CartException;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Cart\Support\CartDeadlockRetry;
use App\Domains\Cart\Support\CartLogger;
use Illuminate\Support\Facades\DB;

final class CartMergeService
{
    public function __construct(
        private readonly CartResolver $resolver,
        private readonly CartValidationService $validation,
        private readonly CartAvailabilityService $availability,
        private readonly CartPresenter $presenter,
        private readonly CartDeadlockRetry $retry,
        private readonly CartLogger $logger,
    ) {}

    public function merge(CartActorData $actor): CartMutationResultData
    {
        if ($actor->userId === null) {
            throw CartException::notMutable();
        }

        return $this->retry->run(function () use ($actor): CartMutationResultData {
            return DB::transaction(function () use ($actor): CartMutationResultData {
                $guestHash = $this->resolver->hashGuestToken($actor->rawGuestToken);
                $userCart = Cart::query()
                    ->where('user_id', $actor->userId)
                    ->where('status', CartStatus::Active)
                    ->first();

                $guestCart = $guestHash === null
                    ? null
                    : Cart::query()
                        ->where('guest_token_hash', $guestHash)
                        ->where('status', CartStatus::Active)
                        ->first();

                if ($guestCart === null) {
                    if ($userCart === null) {
                        return new CartMutationResultData($this->presenter->empty($actor->currency));
                    }

                    return new CartMutationResultData(
                        $this->presenter->present($userCart, $actor->locale, $actor->priceListId),
                    );
                }

                if ($userCart !== null && $userCart->id === $guestCart->id) {
                    return new CartMutationResultData(
                        $this->presenter->present($userCart, $actor->locale, $actor->priceListId),
                    );
                }

                $ids = $userCart === null
                    ? [$guestCart->id]
                    : [$guestCart->id, $userCart->id];
                sort($ids);

                $locked = Cart::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
                $guestCart = $locked->get($guestCart->id);
                $userCart = $userCart !== null ? $locked->get($userCart->id) : null;

                if (! $guestCart instanceof Cart || $guestCart->status !== CartStatus::Active) {
                    $target = $userCart instanceof Cart
                        ? $this->presenter->present($userCart, $actor->locale, $actor->priceListId)
                        : $this->presenter->empty($actor->currency);

                    return new CartMutationResultData($target, forgetGuestCookie: true);
                }

                if ($userCart === null) {
                    $guestCart->user_id = $actor->userId;
                    $guestCart->guest_token_hash = null;
                    $guestCart->version = $guestCart->version + 1;
                    $guestCart->save();
                    $snapshot = $this->presenter->present($guestCart, $actor->locale, $actor->priceListId);
                    $this->logger->info('merged_adopted', ['cart_public_id' => $guestCart->public_id]);

                    DB::afterCommit(function () use ($guestCart): void {
                        event(new CartMerged($guestCart->public_id, $guestCart->public_id, false));
                    });

                    return new CartMutationResultData($snapshot, forgetGuestCookie: true);
                }

                if ($userCart->status !== CartStatus::Active) {
                    throw CartException::notMutable();
                }

                $adjusted = $this->moveLines($userCart, $guestCart, $actor);
                $guestCart->status = CartStatus::Merged;
                $guestCart->merged_into_cart_id = $userCart->id;
                $guestCart->guest_token_hash = null;
                $guestCart->save();

                $userCart->version = $userCart->version + 1;
                $userCart->save();

                $snapshot = $this->presenter->present($userCart->fresh() ?? $userCart, $actor->locale, $actor->priceListId);
                if ($adjusted) {
                    $snapshot = $this->withMergeIssue($snapshot);
                }

                $this->logger->info('merged', [
                    'destination' => $userCart->public_id,
                    'source' => $guestCart->public_id,
                    'adjusted' => $adjusted,
                ]);

                DB::afterCommit(function () use ($userCart, $guestCart, $adjusted): void {
                    event(new CartMerged($userCart->public_id, $guestCart->public_id, $adjusted));
                });

                return new CartMutationResultData($snapshot, forgetGuestCookie: true);
            });
        });
    }

    private function moveLines(Cart $destination, Cart $source, CartActorData $actor): bool
    {
        $adjusted = false;
        $sourceItems = CartItem::query()->where('cart_id', $source->id)->lockForUpdate()->get();
        $stock = $this->availability->forVariants(
            $sourceItems->pluck('variant_id')->map(fn ($id): int => (int) $id)->all(),
        );
        $maxLine = $this->validation->maxLineQuantity();
        $maxLines = max(1, (int) config('cart.max_unique_lines', 50));

        foreach ($sourceItems as $sourceItem) {
            $destItem = CartItem::query()
                ->where('cart_id', $destination->id)
                ->where('variant_id', $sourceItem->variant_id)
                ->lockForUpdate()
                ->first();

            $availability = $stock[$sourceItem->variant_id] ?? null;
            $stockCap = $availability !== null ? max(0, $availability->availableToSell) : 0;
            $cap = min($maxLine, $stockCap);

            if ($destItem === null) {
                $unique = CartItem::query()->where('cart_id', $destination->id)->count();
                if ($unique >= $maxLines) {
                    $adjusted = true;
                    $sourceItem->delete();

                    continue;
                }

                $qty = min($sourceItem->quantity, $cap);
                if ($qty < $sourceItem->quantity) {
                    $adjusted = true;
                }
                if ($qty < 1) {
                    $adjusted = true;
                    $sourceItem->delete();

                    continue;
                }

                $sourceItem->cart_id = $destination->id;
                $sourceItem->quantity = $qty;
                $sourceItem->save();

                continue;
            }

            $merged = $destItem->quantity + $sourceItem->quantity;
            $qty = min($merged, $cap);
            if ($qty < $merged) {
                $adjusted = true;
            }
            if ($qty < 1) {
                $adjusted = true;
                $sourceItem->delete();

                continue;
            }

            $destItem->quantity = $qty;
            $destItem->save();
            $sourceItem->delete();
        }

        return $adjusted;
    }

    private function withMergeIssue(CartSnapshotData $snapshot): CartSnapshotData
    {
        $issues = $snapshot->issues;
        $issues[] = new CartIssueData(
            CartIssueCode::QuantityAdjustedOnMerge,
            'Some quantities were adjusted to match current availability and limits.',
        );

        return new CartSnapshotData(
            publicId: $snapshot->publicId,
            status: $snapshot->status,
            version: $snapshot->version,
            currency: $snapshot->currency,
            itemCount: $snapshot->itemCount,
            uniqueItemCount: $snapshot->uniqueItemCount,
            items: $snapshot->items,
            itemsSubtotalMinor: $snapshot->itemsSubtotalMinor,
            discountTotalMinor: $snapshot->discountTotalMinor,
            cartTotalMinor: $snapshot->cartTotalMinor,
            issues: $issues,
            updatedAt: $snapshot->updatedAt,
        );
    }
}
