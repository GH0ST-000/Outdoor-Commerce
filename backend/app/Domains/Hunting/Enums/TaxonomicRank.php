<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum TaxonomicRank: string
{
    case Kingdom = 'kingdom';
    case Phylum = 'phylum';
    case ClassName = 'class';
    case Order = 'order';
    case Family = 'family';
    case Genus = 'genus';
    case Species = 'species';
    case Subspecies = 'subspecies';
}
