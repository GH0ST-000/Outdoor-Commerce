<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Restores a soft-deleted product to draft. Does not auto-activate.
 */
final class RestoreProductAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Product $product,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Product {
        $updated = DB::transaction(function () use (
            $product,
            $actor,
            $requestId,
            $ipAddress,
            $userAgent,
        ): Product {
            /** @var Product $locked */
            $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->restore();
            $locked->status = ProductStatus::Draft;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductRestored,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: ['status' => ProductStatus::Draft->value],
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
