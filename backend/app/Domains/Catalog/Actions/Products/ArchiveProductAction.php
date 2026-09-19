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

final class ArchiveProductAction
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
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status->value;

            $locked->status = ProductStatus::Archived;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();
            $locked->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductArchived,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => ProductStatus::Archived->value],
            ));

            return $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
