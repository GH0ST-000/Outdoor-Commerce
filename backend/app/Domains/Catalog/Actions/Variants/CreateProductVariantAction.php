<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Variants;

use App\Domains\Catalog\DTOs\Variants\ProductVariantWriteData;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Services\Variants\BarcodeService;
use App\Domains\Catalog\Services\Variants\DefaultVariantService;
use App\Domains\Catalog\Services\Variants\SkuService;
use App\Domains\Catalog\Services\Variants\VariantCombinationService;
use App\Domains\Catalog\Services\Variants\VariantGenerationService;
use App\Domains\Catalog\Services\Variants\VariantReadinessService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CreateProductVariantAction
{
    public function __construct(
        private readonly VariantCombinationService $combinations,
        private readonly SkuService $skus,
        private readonly BarcodeService $barcodes,
        private readonly DefaultVariantService $defaults,
        private readonly VariantGenerationService $generation,
        private readonly VariantReadinessService $readiness,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Product $product,
        ProductVariantWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ProductVariant {
        $variant = DB::transaction(function () use ($product, $data, $actor, $requestId, $ipAddress, $userAgent): ProductVariant {
            $locked = $this->defaults->lockProduct($product);
            $actorId = (int) $actor->getAuthIdentifier();

            $this->generation->assertProductVariantCapacity($locked, 1);

            $combination = $this->combinations->build(
                $locked,
                $data->attributeValues ?? [],
                $data->status,
            );
            $this->combinations->assertUnique($locked, $combination);

            $sku = $data->sku !== null && trim($data->sku) !== ''
                ? $this->skus->fromInput($data->sku)
                : $this->skus->generate($locked);
            $this->skus->assertUnique($sku);

            $barcode = $this->barcodes->fromInput($data->barcode);
            $this->barcodes->assertUnique($barcode);

            $variant = new ProductVariant;
            $variant->product_id = $locked->id;
            $variant->sku = $sku->value;
            $variant->barcode = $barcode?->value;
            $variant->status = $data->status;
            $variant->is_default = false;
            $variant->sort_order = max(0, $data->sortOrder);
            $variant->combination_hash = $combination->hash;
            $variant->combination_signature = $combination->signature;
            $variant->created_by = $actorId;
            $variant->updated_by = $actorId;
            $variant->save();

            $this->combinations->persist($variant, $combination);
            $variant->save();

            if ($data->status === ProductVariantStatus::Active) {
                $this->readiness->assertReadyForActivation($variant);
            }

            $hasDefault = ProductVariant::query()
                ->where('product_id', $locked->id)
                ->whereKeyNot($variant->id)
                ->where('is_default', true)
                ->whereIn('status', [ProductVariantStatus::Draft->value, ProductVariantStatus::Active->value])
                ->exists();

            if ($data->isDefault === true || ! $hasDefault) {
                $this->defaults->setDefault($locked, $variant);
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::ProductVariantCreated,
                actorUserId: $actorId,
                subjectType: 'product_variant',
                subjectId: (string) $variant->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'status' => $variant->status->value,
                    'is_default' => $variant->is_default,
                    'combination_signature' => $variant->combination_signature,
                ],
            ));

            return $variant->fresh(['combinationRows.attributeValue.translations', 'combinationRows.attribute.translations']) ?? $variant;
        });

        $this->catalogCache->bump();

        return $variant;
    }
}
