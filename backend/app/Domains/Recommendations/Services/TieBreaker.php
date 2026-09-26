<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

/**
 * Tie-break order:
 * 1. Pinned eligible products first, then lower pin priority.
 * 2. Final score descending.
 * 3. Confidence rank descending.
 * 4. In stock before out of stock.
 * 5. Lower merchandising priority.
 * 6. Newer publication timestamp.
 * 7. Slug ascending.
 *
 * @phpstan-type Ranked array{
 *     pinned: bool,
 *     pin_priority: int,
 *     final_score: int,
 *     confidence_rank: int,
 *     in_stock: bool,
 *     merchandising_priority: int,
 *     published_at: int,
 *     slug: string
 * }
 */
final class TieBreaker
{
    /**
     * @param  list<Ranked>  $rows
     * @return list<Ranked>
     */
    public function sort(array $rows): array
    {
        usort($rows, function (array $left, array $right): int {
            $steps = [
                ((int) $right['pinned']) <=> ((int) $left['pinned']),
                $left['pin_priority'] <=> $right['pin_priority'],
                $right['final_score'] <=> $left['final_score'],
                $right['confidence_rank'] <=> $left['confidence_rank'],
                ((int) $right['in_stock']) <=> ((int) $left['in_stock']),
                $left['merchandising_priority'] <=> $right['merchandising_priority'],
                $right['published_at'] <=> $left['published_at'],
                strcmp($left['slug'], $right['slug']),
            ];
            foreach ($steps as $step) {
                if ($step !== 0) {
                    return $step;
                }
            }

            return 0;
        });

        return $rows;
    }
}
