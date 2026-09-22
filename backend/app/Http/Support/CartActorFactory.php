<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\Support\CartTokenHasher;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use Illuminate\Http\Request;

final class CartActorFactory
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly GuestCartCookie $cookie,
        private readonly CartTokenHasher $tokens,
    ) {}

    public function fromRequest(Request $request, ?string $idempotencyKey = null, ?string $endpoint = null, ?int $expectedVersion = null): CartActorData
    {
        $context = $this->contexts->fromRequest($request);
        $user = $request->user();
        $raw = $this->cookie->rawToken($request);
        $issued = null;

        if ($user === null && ($raw === null || $raw === '') && $idempotencyKey !== null) {
            $raw = $this->tokens->generate();
            $issued = $raw;
        }

        return new CartActorData(
            userId: $user !== null ? (int) $user->getAuthIdentifier() : null,
            rawGuestToken: $raw,
            locale: $context->locale,
            currency: $context->currency,
            priceListId: $context->priceListId,
            idempotencyKey: $idempotencyKey,
            endpoint: $endpoint,
            expectedVersion: $expectedVersion,
            issuedGuestToken: $issued,
        );
    }
}
