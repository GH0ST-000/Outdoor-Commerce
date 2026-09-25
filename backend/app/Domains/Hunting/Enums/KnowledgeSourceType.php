<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum KnowledgeSourceType: string
{
    case Government = 'government';
    case ScientificDatabase = 'scientific_database';
    case ScientificPaper = 'scientific_paper';
    case Book = 'book';
    case ExpertReview = 'expert_review';
    case Organization = 'organization';
    case Other = 'other';
}
