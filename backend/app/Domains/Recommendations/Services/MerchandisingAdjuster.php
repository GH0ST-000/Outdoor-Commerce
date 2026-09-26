<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

/**
 * Merchandising is applied only after hard eligibility.
 * Boosts cannot exceed the configured maximum or a score of 100.
 */
final class MerchandisingAdjuster
{
    /**
     * @return array{adjustment: int, final: int, pinned: bool, pin_priority: int, excluded: bool, promoted: bool, label: string|null}
     */
    public function apply(int $baseScore, ?string $type, int $value, int $priority, int $maxPoints, bool $paid): array
    {
        $maxPoints = max(0, $maxPoints);
        $excluded = $type === 'exclude';
        $pinned = $type === 'pin';
        $adjustment = 0;
        if ($type === 'boost') {
            $adjustment = min($maxPoints, max(0, $value));
        }
        if ($type === 'demote') {
            $adjustment = -min($maxPoints, max(0, $value));
        }
        $final = max(0, min(100, $baseScore + $adjustment));

        return [
            'adjustment' => $adjustment,
            'final' => $final,
            'pinned' => $pinned,
            'pin_priority' => $pinned ? $priority : 100000,
            'excluded' => $excluded,
            'promoted' => $paid || $type === 'boost' || $pinned,
            'label' => ($paid || $type === 'boost' || $pinned) ? 'promoted' : null,
        ];
    }
}
