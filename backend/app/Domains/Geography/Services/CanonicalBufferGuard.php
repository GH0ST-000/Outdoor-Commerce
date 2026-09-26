<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

/**
 * Legal buffer distances stay on the cited rule until a reviewed canonical
 * geometry version exists. This does not build a polygon.
 */
final class CanonicalBufferGuard
{
    /**
     * @return array{generated: false, meters: int, legal_source_reference: string, reason: string}
     */
    public function plan(int $meters, string $legalSourceReference): array
    {
        if ($meters <= 0) {
            throw new \InvalidArgumentException('Buffer distance must be positive.');
        }

        return [
            'generated' => false,
            'meters' => $meters,
            'legal_source_reference' => $legalSourceReference,
            'reason' => 'A legal buffer is not generated until canonical geometry has been reviewed and published. Display geometry is not a source.',
        ];
    }
}
