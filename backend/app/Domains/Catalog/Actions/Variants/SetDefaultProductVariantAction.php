<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\DefaultVariantService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetDefaultProductVariantAction
{
    public function __construct(
        private readonly DefaultVariantService $defaults,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        ProductVariant $variant,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ProductVariant {
        $updated = DB::transaction(function () use ($variant, $actor, $requestId, $ipAddress, $userAgent): ProductVariant {
            $product = $variant->product;
            if ($product === null) {
                throw ValidationException::withMessages(['product' => ['The parent product is missing.']]);
            }

            $locked = $this->defaults->lockProduct($product);

            $previousId = ProductVariant::query()
                ->where('product_id', $locked->id)
                ->where('is_default', true)
                ->value('id');

            /** @var ProductVariant $target */
            $target = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $this->defaults->setDefault($locked, $target);
            $target->updated_by = (int) $actor->getAuthIdentifier();
            $target->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantDefaultChanged,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product_variant',
                subjectId: (string) $target->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['default_variant_id' => $previousId !== null ? (int) $previousId : null],
                newValues: ['default_variant_id' => $target->id],
            ));

            return $target->fresh(['combinationRows.attributeValue.translations', 'combinationRows.attribute.translations']) ?? $target;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
