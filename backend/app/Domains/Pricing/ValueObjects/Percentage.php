<?php

declare(strict_types=1);

namespace App\Domains\Pricing\ValueObjects;

use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;

/**
 * Percentage as basis points. 100 = 1%, 10000 = 100%.
 */
final readonly class Percentage
{
    private function __construct(
        public int $basisPoints,
    ) {}

    public static function fromBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 1 || $basisPoints > 10_000) {
            throw new InvalidMoneyAmountException('Percentage must be between 1 and 10000 basis points.');
        }

        return new self($basisPoints);
    }

    /**
     * @return array{basis_points: int}
     */
    public function toArray(): array
    {
        return ['basis_points' => $this->basisPoints];
    }
}
