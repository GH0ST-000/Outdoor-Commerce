<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\DefaultVariantService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archives a variant and soft-deletes it. Archiving the default variant requires
 * a replacement in the same transaction whenever other variants remain.
 */
final class ArchiveProductVariantAction
{
    public function __construct(
        private readonly DefaultVariantService $defaults,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        ProductVariant $variant,
        Authenticatable $actor,
        ?int $replacementVariantId = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ProductVariant {
        $updated = DB::transaction(function () use (
            $variant,
            $actor,
            $replacementVariantId,
            $requestId,
            $ipAddress,
            $userAgent,
        ): ProductVariant {
            $product = $variant->product;
            if ($product === null) {
                throw ValidationException::withMessages(['product' => ['The parent product is missing.']]);
            }

            $locked = $this->defaults->lockProduct($product);

            /** @var ProductVariant $target */
            $target = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $target->status->value;
            $wasDefault = $target->is_default;

            $replacement = $this->defaults->replaceDefaultForArchive($locked, $target, $replacementVariantId);

            $target->status = ProductVariantStatus::Archived;
            $target->is_default = false;
            $target->updated_by = (int) $actor->getAuthIdentifier();
            $target->save();
            $target->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantArchived,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product_variant',
                subjectId: (string) $target->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus, 'is_default' => $wasDefault],
                newValues: [
                    'status' => ProductVariantStatus::Archived->value,
                    'is_default' => false,
                    'replacement_variant_id' => $replacement?->id,
                ],
            ));

            return $target;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
