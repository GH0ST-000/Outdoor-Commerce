<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Events;

final readonly class SpeciesPublished
{
    public function __construct(
        public int $speciesId,
        public string $publicId,
        public int $contentVersion,
    ) {}
}
