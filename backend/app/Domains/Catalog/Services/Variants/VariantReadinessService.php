<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\Sku;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Requirements a variant must satisfy to be (or stay) active.
 */
final class VariantReadinessService
{
    public function __construct(private readonly VariantCombinationService $combinationService) {}

    /**
     * @return array{ready: bool, issues: array<string, list<string>>, issue_count: int}
     */
    public function evaluate(ProductVariant $variant): array
    {
        $variant->loadMissing(['product.variantAttributes', 'combinationRows.attributeValue', 'combinationRows.attribute']);

        /** @var array<string, list<string>> $issues */
        $issues = [];

        if ($variant->trashed()) {
            $issues['variant'] = ['A deleted variant cannot be activated.'];
        }

        try {
            Sku::assertValid(Sku::normalize($variant->sku));
        } catch (InvalidArgumentException $e) {
            $issues['sku'] = [$e->getMessage()];
        }

        $product = $variant->product;
        if ($product === null || $product->trashed()) {
            $issues['product'] = ['The parent product must exist and not be deleted.'];

            return $this->result($issues);
        }

        if ($product->status === ProductStatus::Archived) {
            $issues['product'] = ['Archived products cannot hold active variants.'];
        }

        $axisIds = $this->combinationService->axisIds($product);
        $assigned = [];

        foreach ($variant->combinationRows as $row) {
            $attributeId = (int) $row->attribute_id;
            $assigned[$attributeId] = true;

            if (! in_array($attributeId, $axisIds, true)) {
                $issues['attribute_values'] ??= [];
                $issues['attribute_values'][] = "Attribute [{$attributeId}] is no longer a variant axis of the product.";

                continue;
            }

            $value = $row->attributeValue;
            if ($value === null || $value->trashed()) {
                $issues['attribute_values'] ??= [];
                $issues['attribute_values'][] = "Attribute value [{$row->attribute_value_id}] is missing or deleted.";

                continue;
            }

            if ($value->status !== AttributeValueStatus::Active) {
                $issues['attribute_values'] ??= [];
                $issues['attribute_values'][] = "Attribute value [{$value->id}] must be active.";
            }

            $attribute = $row->attribute;
            if ($attribute !== null && $attribute->status !== AttributeStatus::Active) {
                $issues['attribute_values'] ??= [];
                $issues['attribute_values'][] = "Attribute [{$attribute->id}] must be active.";
            }
        }

        $missing = array_values(array_diff($axisIds, array_keys($assigned)));
        if ($missing !== []) {
            $issues['attribute_values'] ??= [];
            $issues['attribute_values'][] = 'An active variant needs exactly one value for every variant axis.';
        }

        return $this->result($issues);
    }

    public function assertReadyForActivation(ProductVariant $variant): void
    {
        $result = $this->evaluate($variant);

        if (! $result['ready']) {
            throw ValidationException::withMessages($result['issues']);
        }
    }

    /**
     * @param  array<string, list<string>>  $issues
     * @return array{ready: bool, issues: array<string, list<string>>, issue_count: int}
     */
    private function result(array $issues): array
    {
        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'issue_count' => count($issues),
        ];
    }
}
