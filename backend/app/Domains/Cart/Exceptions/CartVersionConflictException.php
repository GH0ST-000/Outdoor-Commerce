<?php

declare(strict_types=1);

namespace App\Domains\Cart\Exceptions;

use App\Domains\Cart\DTOs\CartSnapshotData;
use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

final class CartVersionConflictException extends DomainException implements ProvidesErrorDetails
{
    public function __construct(private readonly CartSnapshotData $cart)
    {
        parent::__construct('The cart was updated in another request. Refresh and try again.', 'CART_VERSION_CONFLICT');
    }

    public function cart(): CartSnapshotData
    {
        return $this->cart;
    }

    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array
    {
        return [
            'cart' => $this->cart->toArray(),
        ];
    }
}
