<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum SpeciesAliasType: string
{
    case CommonAlias = 'common_alias';
    case RegionalName = 'regional_name';
    case HistoricalName = 'historical_name';
    case ScientificSynonym = 'scientific_synonym';
    case MisspellingAlias = 'misspelling_alias';
    case Transliteration = 'transliteration';
}
