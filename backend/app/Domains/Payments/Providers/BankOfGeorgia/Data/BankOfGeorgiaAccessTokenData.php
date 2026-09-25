<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia\Data;

use Carbon\CarbonImmutable;

final readonly class BankOfGeorgiaAccessTokenData
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public CarbonImmutable $expiresAt,
    ) {}

    public function isUsable(CarbonImmutable $now, int $skewSeconds): bool
    {
        return $this->expiresAt->subSeconds(max(0, $skewSeconds))->gt($now);
    }
}
