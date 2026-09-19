<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\DTOs\Variants\VariantGenerationData;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\DefaultVariantService;
use App\Domains\Catalog\Services\Variants\VariantGenerationService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class GenerateProductVariantsAction
{
    public function __construct(
        private readonly VariantGenerationService $generation,
        private readonly DefaultVariantService $defaults,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    /**
     * @return array{created: list<ProductVariant>, summary: array{requested: int, created: int, skipped: int, limit: int}}
     */
    public function execute(
        Product $product,
        VariantGenerationData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $result = DB::transaction(function () use ($product, $data, $actor, $requestId, $ipAddress, $userAgent): array {
            $locked = $this->defaults->lockProduct($product);
            $actorId = (int) $actor->getAuthIdentifier();

            $result = $this->generation->generate($locked, $data, $actorId);
            $this->defaults->ensureSingleDefault($locked);

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantsGenerated,
                actorUserId: $actorId,
                subjectType: 'product',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'requested' => $result['summary']['requested'],
                    'created' => $result['summary']['created'],
                    'skipped' => $result['summary']['skipped'],
                    'status' => $data->status->value,
                    'variant_ids' => array_map(
                        static fn (ProductVariant $variant): int => (int) $variant->id,
                        $result['created'],
                    ),
                ],
            ));

            return $result;
        });

        $this->catalogCache->bump();

        return $result;
    }
}
