<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Actions;

use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\DTOs\CheckoutAddressWriteData;
use App\Domains\Checkout\DTOs\CheckoutMutationResultData;
use App\Domains\Checkout\Services\CheckoutSessionService;

final class UpdateCheckoutAddressAction
{
    public function __construct(private readonly CheckoutSessionService $sessions) {}

    public function execute(CheckoutActorData $actor, string $publicId, CheckoutAddressWriteData $address): CheckoutMutationResultData
    {
        return $this->sessions->updateAddress($actor, $publicId, $address);
    }
}
