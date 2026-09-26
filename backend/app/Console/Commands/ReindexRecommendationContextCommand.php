<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Catalog\Contracts\SearchGateway;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Enums\AssignmentStatus;
use App\Domains\Recommendations\Enums\AssignmentType;
use App\Domains\Recommendations\Models\ProductContextAssignment;
use App\Domains\Recommendations\Services\RecommendationAuditRecorder;
use Illuminate\Console\Command;

class ReindexRecommendationContextCommand extends Command
{
    protected $signature = 'recommendations:reindex {--actor= : Administrator user id for the audit row}';

    protected $description = 'Rebuild the optional Meilisearch candidate index from active MySQL context assignments';

    public function handle(SearchGateway $search, RecommendationAuditRecorder $audit): int
    {
        $uid = (string) config('search.index_prefix', 'outdoor').'_'.(string) config('recommendations.search_index', 'recommendation_context');
        $search->ensureIndex($uid, 'id', [
            'filterableAttributes' => ['product_id', 'context_codes', 'exclusion_codes'],
            'sortableAttributes' => ['product_id'],
        ]);
        $grouped = [];
        ProductContextAssignment::query()
            ->with('term')
            ->where('status', AssignmentStatus::Active)
            ->orderBy('id')
            ->each(function (ProductContextAssignment $assignment) use (&$grouped): void {
                $term = $assignment->term;
                if ($term === null) {
                    return;
                }
                $code = $term->dimension->value.':'.$term->code;
                $bucket = $assignment->assignment_type === AssignmentType::Excluded ? 'exclusion_codes' : 'context_codes';
                $grouped[$assignment->product_id][$bucket][] = $code;
            });
        $documents = [];
        foreach ($grouped as $productId => $codes) {
            $documents[] = [
                'id' => 'product_'.$productId,
                'product_id' => (int) $productId,
                'context_codes' => array_values(array_unique($codes['context_codes'] ?? [])),
                'exclusion_codes' => array_values(array_unique($codes['exclusion_codes'] ?? [])),
            ];
        }
        if ($documents !== []) {
            $search->upsertDocuments($uid, $documents);
        }
        $actor = $this->option('actor') !== null ? User::query()->find((int) $this->option('actor')) : null;
        if ($actor !== null) {
            $audit->record(AuditEvent::RecommendationReindexed, $actor, 'recommendation_index', $uid, null, [
                'documents' => count($documents),
            ]);
        }
        $this->info('Indexed '.count($documents).' products into '.$uid);

        return self::SUCCESS;
    }
}
