<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

use App\Domains\Cart\Enums\CartIssueCode;

final readonly class CartIssueData
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public CartIssueCode $code,
        public string $message,
        public ?string $itemPublicId = null,
        public ?array $context = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code->value,
            'message' => $this->message,
            'item_id' => $this->itemPublicId,
            'context' => $this->context,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
