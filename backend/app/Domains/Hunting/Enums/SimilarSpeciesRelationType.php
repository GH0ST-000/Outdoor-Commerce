<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SimilarSpeciesRelationType: string
{
    case VisuallySimilar = 'visually_similar';
    case CommonlyConfused = 'commonly_confused';
    case RelatedSpecies = 'related_species';
    case SameFamily = 'same_family';
}
