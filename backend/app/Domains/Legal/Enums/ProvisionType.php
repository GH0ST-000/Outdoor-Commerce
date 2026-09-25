<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum ProvisionType: string
{
    case Article = 'article';
    case Section = 'section';
    case Subsection = 'subsection';
    case Paragraph = 'paragraph';
    case Clause = 'clause';
    case Subclause = 'subclause';
    case Appendix = 'appendix';
    case Table = 'table';
    case TableRow = 'table_row';
    case Note = 'note';
    case Other = 'other';
}
