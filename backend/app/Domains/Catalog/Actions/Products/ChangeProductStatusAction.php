<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeProductStatusAction
{
    public function __construct(
        private readonly ProductReadinessService $readinessService,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Product $product,
        ProductStatus $status,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Product {
        if ($status === ProductStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => ['Use the archive endpoint to archive a product.'],
            ]);
        }

        $updated = DB::transaction(function () use (
            $product,
            $status,
            $actor,
            $requestId,
            $ipAddress,
            $userAgent,
        ): Product {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status;

            if ($oldStatus === $status) {
                return $locked;
            }

            if ($status === ProductStatus::Active) {
                $locked->load(['translations', 'categories', 'brand', 'primaryCategory']);
                $this->readinessService->assertReadyForActivation($locked);
                if ($locked->published_at === null) {
                    $locked->published_at = now();
                }
            }

            $locked->status = $status;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductStatusChanged,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
            ));

            return $locked->fresh([
                'translations',
                'categories.translations',
                'brand.translations',
                'primaryCategory.translations',
            ]) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
