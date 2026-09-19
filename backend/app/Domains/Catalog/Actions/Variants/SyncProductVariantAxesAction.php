<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\DTOs\Variants\VariantAxesData;
use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Exceptions\VariantAxisConflictException;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the ordered set of variant axes on a product.
 *
 * Axes still resolved by non-archived variants cannot be removed, and new axes
 * cannot be added while active variants exist because those variants would
 * silently lose the "one value per axis" guarantee.
 */
final class SyncProductVariantAxesAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Product $product,
        VariantAxesData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Product {
        $updated = DB::transaction(function () use ($product, $data, $actor, $requestId, $ipAddress, $userAgent): Product {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->load('variantAttributes');

            $current = $locked->variantAttributes->map(static fn ($a): int => (int) $a->id)->values()->all();
            $desired = [];
            $seen = [];

            foreach ($data->axes as $index => $axis) {
                $attributeId = (int) $axis['attribute_id'];

                if (isset($seen[$attributeId])) {
                    throw ValidationException::withMessages([
                        "axes.{$index}.attribute_id" => ['Each attribute may appear only once.'],
                    ]);
                }
                $seen[$attributeId] = true;

                /** @var Attribute|null $attribute */
                $attribute = Attribute::query()->whereKey($attributeId)->first();

                if ($attribute === null) {
                    throw ValidationException::withMessages([
                        "axes.{$index}.attribute_id" => ['The selected attribute is invalid.'],
                    ]);
                }

                if ($attribute->status !== AttributeStatus::Active && ! in_array($attributeId, $current, true)) {
                    throw ValidationException::withMessages([
                        "axes.{$index}.attribute_id" => ['Only active attributes can be used as variant axes.'],
                    ]);
                }

                $desired[$attributeId] = max(0, (int) ($axis['sort_order'] ?? $index));
            }

            $desiredIds = array_map('intval', array_keys($desired));
            $removed = array_values(array_diff($current, $desiredIds));
            $added = array_values(array_diff($desiredIds, $current));

            if ($removed !== []) {
                $conflictingVariantIds = ProductVariant::query()
                    ->where('product_id', $locked->id)
                    ->where('status', '!=', ProductVariantStatus::Archived->value)
                    ->whereHas('combinationRows', fn ($rows) => $rows->whereIn('attribute_id', $removed))
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if ($conflictingVariantIds !== []) {
                    throw new VariantAxisConflictException($removed, $conflictingVariantIds);
                }
            }

            if ($added !== []) {
                $activeVariantIds = ProductVariant::query()
                    ->where('product_id', $locked->id)
                    ->where('status', ProductVariantStatus::Active->value)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                if ($activeVariantIds !== []) {
                    throw new VariantAxisConflictException($added, $activeVariantIds);
                }
            }

            $pivot = [];
            foreach ($desired as $attributeId => $sortOrder) {
                $pivot[(int) $attributeId] = ['sort_order' => $sortOrder];
            }

            $locked->variantAttributes()->sync($pivot);
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantAxesUpdated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['attribute_ids' => $current],
                newValues: ['attribute_ids' => $desiredIds],
            ));

            return $locked->fresh(['variantAttributes.translations', 'variants']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
