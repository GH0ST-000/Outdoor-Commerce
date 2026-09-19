<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\DTOs\Variants\ProductVariantWriteData;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\BarcodeService;
use App\Domains\Catalog\Services\Variants\DefaultVariantService;
use App\Domains\Catalog\Services\Variants\SkuService;
use App\Domains\Catalog\Services\Variants\VariantCombinationService;
use App\Domains\Catalog\Services\Variants\VariantReadinessService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProductVariantAction
{
    public function __construct(
        private readonly VariantCombinationService $combinations,
        private readonly SkuService $skus,
        private readonly BarcodeService $barcodes,
        private readonly DefaultVariantService $defaults,
        private readonly VariantReadinessService $readiness,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        ProductVariant $variant,
        ProductVariantWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ProductVariant {
        $updated = DB::transaction(function () use ($variant, $data, $actor, $requestId, $ipAddress, $userAgent): ProductVariant {
            $product = $variant->product;
            if ($product === null) {
                throw ValidationException::withMessages(['product' => ['The parent product is missing.']]);
            }

            $locked = $this->defaults->lockProduct($product);

            /** @var ProductVariant $current */
            $current = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $actorId = (int) $actor->getAuthIdentifier();

            $old = [
                'sku' => $current->sku,
                'barcode' => $current->barcode,
                'status' => $current->status->value,
                'sort_order' => $current->sort_order,
                'combination_signature' => $current->combination_signature,
            ];

            if ($data->status === ProductVariantStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Use the archive endpoint to archive a variant.'],
                ]);
            }

            if ($data->sku !== null && trim($data->sku) !== '') {
                $sku = $this->skus->fromInput($data->sku);
                $this->skus->assertUnique($sku, $current->id);
                $current->sku = $sku->value;
            }

            if ($data->barcodeProvided) {
                $barcode = $this->barcodes->fromInput($data->barcode);
                $this->barcodes->assertUnique($barcode, $current->id);
                $current->barcode = $barcode?->value;
            }

            $current->sort_order = max(0, $data->sortOrder);
            $current->status = $data->status;
            $current->updated_by = $actorId;

            if ($data->attributeValues !== null) {
                $combination = $this->combinations->build($locked, $data->attributeValues, $data->status);
                $this->combinations->assertUnique($locked, $combination, $current->id);
                $this->combinations->persist($current, $combination);
            }

            $current->save();

            if ($data->status === ProductVariantStatus::Active) {
                $current->unsetRelation('combinationRows');
                $this->readiness->assertReadyForActivation($current);
            }

            if ($data->isDefault === true) {
                $this->defaults->setDefault($locked, $current);
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantUpdated,
                actorUserId: $actorId,
                subjectType: 'product_variant',
                subjectId: (string) $current->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: [
                    'sku' => $current->sku,
                    'barcode' => $current->barcode,
                    'status' => $current->status->value,
                    'sort_order' => $current->sort_order,
                    'is_default' => $current->is_default,
                    'combination_signature' => $current->combination_signature,
                ],
            ));

            return $current->fresh(['combinationRows.attributeValue.translations', 'combinationRows.attribute.translations']) ?? $current;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
