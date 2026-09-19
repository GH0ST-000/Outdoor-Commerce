<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

/**
 * An axis cannot be removed while non-archived variants still resolve values for it.
 */
final class VariantAxisConflictException extends DomainException implements ProvidesErrorDetails
{
    /**
     * @param  list<int>  $attributeIds
     * @param  list<int>  $variantIds
     */
    public function __construct(
        private readonly array $attributeIds,
        private readonly array $variantIds,
    ) {
        parent::__construct(
            'Variant axes in use cannot be removed. Archive the affected variants first.',
            'VARIANT_AXIS_CONFLICT',
        );
    }

    /**
     * @return array{attribute_ids: list<int>, variant_ids: list<int>}
     */
    public function errorDetails(): array
    {
        return [
            'attribute_ids' => $this->attributeIds,
            'variant_ids' => $this->variantIds,
        ];
    }
}
