<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Jobs;

use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Services\AssignmentWriteService;
use App\Domains\Recommendations\Services\RecommendationAuditRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkAssignProductContextJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $productIds
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public int $actorId,
        public array $productIds,
        public array $input,
    ) {}

    public function handle(AssignmentWriteService $assignments, RecommendationAuditRecorder $audit): void
    {
        $actor = User::query()->findOrFail($this->actorId);
        $failures = [];
        $applied = 0;
        foreach (array_values(array_unique($this->productIds)) as $productId) {
            $product = Product::query()->find($productId);
            if ($product === null) {
                $failures[] = $productId;

                continue;
            }
            try {
                $assignments->upsert($actor, $product, $this->input);
                $applied++;
            } catch (\Throwable) {
                $failures[] = $productId;
            }
        }
        $audit->record(AuditEvent::RecommendationBulkAssignmentCompleted, $actor, 'product_context_assignment', 'bulk', null, [
            'applied' => $applied,
            'failed' => count($failures),
            'failed_product_ids' => array_slice($failures, 0, 50),
        ]);
    }
}
