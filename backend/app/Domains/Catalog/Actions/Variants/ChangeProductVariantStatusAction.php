<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\VariantReadinessService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeProductVariantStatusAction
{
    public function __construct(
        private readonly VariantReadinessService $readiness,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        ProductVariant $variant,
        ProductVariantStatus $status,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ProductVariant {
        if ($status === ProductVariantStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => ['Use the archive endpoint to archive a variant.'],
            ]);
        }

        $updated = DB::transaction(function () use ($variant, $status, $actor, $requestId, $ipAddress, $userAgent): ProductVariant {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status;

            if ($oldStatus === $status) {
                return $locked;
            }

            if ($status === ProductVariantStatus::Active) {
                $this->readiness->assertReadyForActivation($locked);
            }

            $locked->status = $status;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantStatusChanged,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product_variant',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
            ));

            return $locked->fresh(['combinationRows.attributeValue.translations', 'combinationRows.attribute.translations']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
