<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support\Variants;

/**
 * Canonical combination identity. Labels never participate.
 *
 * Signature format: "{attributeId}:{valueId}|..." sorted by attribute ID.
 */
final readonly class VariantCombination
{
    /**
     * @param  list<array{attribute_id: int, attribute_value_id: int}>  $pairs
     */
    public function __construct(
        public string $signature,
        public string $hash,
        public array $pairs,
    ) {}

    /**
     * @param  list<array{attribute_id: int, attribute_value_id: int}>  $pairs
     */
    public static function fromPairs(array $pairs): self
    {
        $normalized = [];
        $seenAttributes = [];

        foreach ($pairs as $pair) {
            $attributeId = (int) $pair['attribute_id'];
            $valueId = (int) $pair['attribute_value_id'];

            if (isset($seenAttributes[$attributeId])) {
                throw new \InvalidArgumentException('Duplicate attribute in combination.');
            }
            $seenAttributes[$attributeId] = true;
            $normalized[] = [
                'attribute_id' => $attributeId,
                'attribute_value_id' => $valueId,
            ];
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => $a['attribute_id'] <=> $b['attribute_id'],
        );

        if ($normalized === []) {
            $signature = '';
        } else {
            $signature = implode('|', array_map(
                static fn (array $pair): string => $pair['attribute_id'].':'.$pair['attribute_value_id'],
                $normalized,
            ));
        }

        return new self(
            signature: $signature,
            hash: hash('sha256', $signature),
            pairs: $normalized,
        );
    }

    public function assertHashMatches(): void
    {
        if ($this->hash !== hash('sha256', $this->signature)) {
            throw new \RuntimeException('Combination hash/signature mismatch.');
        }
    }
}
