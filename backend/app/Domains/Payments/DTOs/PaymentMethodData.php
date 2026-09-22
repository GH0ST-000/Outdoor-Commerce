<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentMethodType;

final readonly class PaymentMethodData
{
    /**
     * @param  list<string>  $supportedCurrencies
     */
    public function __construct(
        public string $code,
        public string $provider,
        public PaymentMethodType $type,
        public string $name,
        public string $description,
        public string $icon,
        public bool $isEnabled,
        public bool $developmentOnly,
        public array $supportedCurrencies,
        public ?int $minimumAmountMinor,
        public ?int $maximumAmountMinor,
        public int $sortOrder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type->value,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'development_only' => $this->developmentOnly,
            'supported_currencies' => $this->supportedCurrencies,
        ];
    }
}
