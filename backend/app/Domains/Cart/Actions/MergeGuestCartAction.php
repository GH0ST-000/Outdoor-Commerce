<?php

declare(strict_types=1);

namespace App\Domains\Cart\Actions;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\DTOs\CartMutationResultData;
use App\Domains\Cart\Services\CartMergeService;

final class MergeGuestCartAction
{
    public function __construct(private readonly CartMergeService $merge) {}

    public function execute(CartActorData $actor): CartMutationResultData
    {
        return $this->merge->merge($actor);
    }
}
