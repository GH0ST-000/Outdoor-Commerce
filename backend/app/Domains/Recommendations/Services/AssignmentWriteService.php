<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Enums\AssignmentSourceType;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Models\ContextTaxonomyTerm;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AssignmentWriteService
{
    public function __construct(
        private readonly RecommendationAuditRecorder $audit,
        private readonly RecommendationCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function upsert(User $actor, Product $product, array $input): ProductContextAssignment
    {
        $term = ContextTaxonomyTerm::query()->where('public_id', $input['term_id'])->where('is_active', true)->first();
        if ($term === null) {
            throw RecommendationException::notFound('Context term');
        }
        $variantId = null;
        if (! empty($input['variant_id'])) {
            $variant = ProductVariant::query()->where('product_id', $product->id)->where('sku', $input['variant_id'])->first();
            if ($variant === null) {
                throw RecommendationException::notFound('Variant');
            }
            $variantId = $variant->id;
        }
        $type = AssignmentType::from($input['assignment_type']);
        $source = AssignmentSourceType::from($input['source_type']);
        if ($source === AssignmentSourceType::LegalRule && empty($input['source_reference_id'])) {
            throw RecommendationException::invalid('A legal-rule assignment must keep its source reference.');
        }

        return DB::transaction(function () use ($actor, $product, $term, $variantId, $type, $source, $input): ProductContextAssignment {
            $attributes = [
                'product_variant_id' => $variantId,
                'weight' => $input['weight'] ?? 1,
                'source_type' => $source,
                'source_reference_type' => $input['source_reference_type'] ?? null,
                'source_reference_id' => $input['source_reference_id'] ?? null,
                'status' => AssignmentStatus::from($input['status'] ?? AssignmentStatus::Active->value),
                'effective_from' => $input['effective_from'] ?? null,
                'effective_until' => $input['effective_until'] ?? null,
                'review_notes' => $input['review_notes'] ?? null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ];
            $assignment = ProductContextAssignment::query()->firstOrNew([
                'product_id' => $product->id,
                'variant_key' => $variantId ?? 0,
                'context_taxonomy_term_id' => $term->id,
                'assignment_type' => $type,
            ]);
            if (! $assignment->exists) {
                $assignment->public_id = (string) Str::uuid();
                $assignment->created_by = $actor->id;
            }
            $assignment->fill($attributes);
            $assignment->save();
            $this->cache->bump();
            $this->audit->record(AuditEvent::RecommendationAssignmentSaved, $actor, 'product_context_assignment', $assignment->public_id, null, [
                'product_id' => $product->id,
                'term' => $term->dimension->value.':'.$term->code,
                'type' => $type->value,
                'source' => $source->value,
                'variant_key' => $variantId ?? 0,
            ]);

            return $assignment->load('term');
        });
    }

    public function delete(User $actor, Product $product, ProductContextAssignment $assignment): void
    {
        if ((int) $assignment->product_id !== $product->id) {
            throw RecommendationException::notFound('Assignment');
        }
        $id = $assignment->public_id;
        $assignment->delete();
        $this->cache->bump();
        $this->audit->record(AuditEvent::RecommendationAssignmentRemoved, $actor, 'product_context_assignment', $id, null, ['product_id' => $product->id]);
    }
}
